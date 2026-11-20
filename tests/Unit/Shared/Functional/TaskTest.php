<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Functional;

use App\Shared\Functional\Task;
use PHPUnit\Framework\TestCase;

final class TaskTest extends TestCase
{
    public function testDoesNotRunUntilAsked(): void
    {
        $calls = 0;
        $task = Task::of(static function () use (&$calls): int {
            ++$calls;

            return 42;
        });

        self::assertSame(0, $calls);

        self::assertSame(42, $task->run());
        self::assertSame(1, $calls);
    }

    public function testMapIsLazyAndTransformsTheValue(): void
    {
        $calls = 0;
        $task = Task::of(static function () use (&$calls): int {
            ++$calls;

            return 2;
        })->map(static fn (int $value): int => $value * 3);

        self::assertSame(0, $calls);

        self::assertSame(6, $task->run());
        self::assertSame(1, $calls);
    }

    public function testFlatMapSequencesTasks(): void
    {
        $order = [];

        $task = Task::of(static function () use (&$order): string {
            $order[] = 'first';

            return 'a';
        })->flatMap(static function (string $value) use (&$order): Task {
            return Task::of(static function () use (&$order, $value): string {
                $order[] = 'second';

                return $value.'b';
            });
        });

        self::assertSame('ab', $task->run());
        self::assertSame(['first', 'second'], $order);
    }

    public function testCanBeRunMoreThanOnce(): void
    {
        $calls = 0;
        $task = Task::of(static function () use (&$calls): int {
            ++$calls;

            return 42;
        });

        self::assertSame([42, 42], [$task->run(), $task->run()]);
        self::assertSame(2, $calls);
    }

    public function testDerivedTaskCanBeRunIndependently(): void
    {
        $base = Task::of(static fn (): int => 1);
        $mapped = $base->map(static fn (int $value): int => $value + 1);

        self::assertSame(2, $mapped->run());
        self::assertSame(1, $base->run());
    }
}
