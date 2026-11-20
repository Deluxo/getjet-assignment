<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Functional;

use App\Shared\Functional\Either;
use App\Shared\Functional\TaskEither;
use PHPUnit\Framework\TestCase;

final class TaskEitherTest extends TestCase
{
    public function testDoesNotRunUntilAsked(): void
    {
        $calls = 0;
        $taskEither = TaskEither::defer(static function () use (&$calls): Either {
            ++$calls;

            return Either::Right(42);
        });

        self::assertSame(0, $calls);

        self::assertSame(
            42,
            $taskEither->run()->fold(
                static fn (): int => -1,
                static fn (int $value): int => $value,
            ),
        );
        self::assertSame(1, $calls);
    }

    public function testOfWrapsValueAsRight(): void
    {
        self::assertSame(
            42,
            TaskEither::of(42)->run()->fold(
                static fn (): int => -1,
                static fn (int $value): int => $value,
            ),
        );
    }

    public function testRightIsReturnedByRun(): void
    {
        self::assertSame(
            1,
            TaskEither::right(1)->run()->fold(
                static fn (): int => -1,
                static fn (int $value): int => $value,
            ),
        );
    }

    public function testLeftIsReturnedByRun(): void
    {
        self::assertSame(
            ['error'],
            TaskEither::left(['error'])->run()->fold(
                static fn (array $error): array => $error,
                static fn (): array => [],
            ),
        );
    }

    public function testFromEitherLiftsBothBranches(): void
    {
        self::assertTrue(TaskEither::fromEither(Either::Right(1))->run()->isRight());
        self::assertTrue(TaskEither::fromEither(Either::Left(['error']))->run()->isLeft());
    }

    public function testMapIsLazyAndTransformsRight(): void
    {
        $calls = 0;
        $taskEither = TaskEither::defer(static function () use (&$calls): Either {
            ++$calls;

            return Either::Right(2);
        })->map(static fn (int $value): int => $value * 3);

        self::assertSame(0, $calls);

        self::assertSame(
            6,
            $taskEither->run()->fold(
                static fn (): int => -1,
                static fn (int $value): int => $value,
            ),
        );
        self::assertSame(1, $calls);
    }

    public function testMapDoesNotRunOnLeft(): void
    {
        $called = false;
        /** @var TaskEither<array<int, string>, int> $left */
        $left = TaskEither::left(['error']);
        $taskEither = $left->map(static function (int $value) use (&$called): int {
            $called = true;

            return $value;
        });

        self::assertFalse($called);
        self::assertTrue($taskEither->run()->isLeft());
    }

    public function testMapLeftTransformsLeftOnly(): void
    {
        self::assertSame(
            'BOOM',
            TaskEither::left('boom')
                ->mapLeft(static fn (string $error): string => strtoupper($error))
                ->run()
                ->fold(static fn (string $error): string => $error, static fn (): string => 'right'),
        );

        self::assertTrue(
            TaskEither::right(1)
                ->mapLeft(static fn (string $error): string => strtoupper($error))
                ->run()
                ->isRight(),
        );
    }

    public function testMapLeftIsLazyAndSkippedOnRight(): void
    {
        $transform = new CountingTransformer();
        $taskEither = TaskEither::right(1)->mapLeft($transform);

        self::assertSame(0, $transform->calls);
        self::assertTrue($taskEither->run()->isRight());
        self::assertSame(0, $transform->calls);
    }

    public function testTapRunsTheEffectOnRightAndPassesTheValueThrough(): void
    {
        $seen = [];
        $taskEither = TaskEither::right(42)->tap(static function (int $value) use (&$seen): void {
            $seen[] = $value;
        });

        self::assertSame([], $seen);
        self::assertSame(
            42,
            $taskEither->run()->fold(
                static fn (): int => -1,
                static fn (int $value): int => $value,
            ),
        );
        self::assertSame([42], $seen);
    }

    public function testTapIsSkippedOnLeft(): void
    {
        $called = false;
        $taskEither = TaskEither::left(['error'])->tap(static function () use (&$called): void {
            $called = true;
        });

        self::assertTrue($taskEither->run()->isLeft());
        self::assertFalse($called);
    }

    public function testFlatMapSequencesTaskEithers(): void
    {
        $order = [];
        $taskEither = TaskEither::defer(static function () use (&$order): Either {
            $order[] = 'first';

            return Either::Right('a');
        })->flatMap(static function (string $value) use (&$order): TaskEither {
            return TaskEither::defer(static function () use (&$order, $value): Either {
                $order[] = 'second';

                return Either::Right($value.'b');
            });
        });

        self::assertSame(
            'ab',
            $taskEither->run()->fold(
                static fn (): string => '',
                static fn (string $value): string => $value,
            ),
        );
        self::assertSame(['first', 'second'], $order);
    }

    public function testFlatMapShortCircuitsOnLeft(): void
    {
        $called = false;
        $taskEither = TaskEither::left(['first'])->flatMap(static function (int $value) use (&$called): TaskEither {
            $called = true;

            return TaskEither::right($value);
        });

        self::assertFalse($called);
        self::assertSame(
            ['first'],
            $taskEither->run()->fold(
                static fn (array $error): array => $error,
                static fn (): array => [],
            ),
        );
    }

    public function testFlatMapPropagatesFailureFromContinuation(): void
    {
        $taskEither = TaskEither::right(1)->flatMap(static fn (int $value): TaskEither => TaskEither::left(['boom']));

        self::assertTrue($taskEither->run()->isLeft());
    }

    public function testApAppliesWrappedFunction(): void
    {
        $taskEither = TaskEither::right(2)->ap(TaskEither::right(static fn (int $value): int => $value * 3));

        self::assertSame(
            6,
            $taskEither->run()->fold(
                static fn (): int => -1,
                static fn (int $value): int => $value,
            ),
        );
    }

    public function testApShortCircuitsWhenValueIsLeft(): void
    {
        $called = false;
        $taskEither = TaskEither::left(['value'])->ap(TaskEither::right(static function (int $value) use (&$called): int {
            $called = true;

            return $value;
        }));

        self::assertFalse($called);
        self::assertTrue($taskEither->run()->isLeft());
    }

    public function testApShortCircuitsWhenFunctionIsLeft(): void
    {
        $taskEither = TaskEither::right(2)->ap(TaskEither::left(['function']));

        self::assertTrue($taskEither->run()->isLeft());
    }

    public function testFoldIsLazyAndPicksTheMatchingBranch(): void
    {
        $calls = 0;
        $rightTask = TaskEither::defer(static function () use (&$calls): Either {
            ++$calls;

            return Either::Right(1);
        })->fold(
            static fn (): string => 'left',
            static fn (int $value): string => 'right:'.$value,
        );

        self::assertSame(0, $calls);
        self::assertSame('right:1', $rightTask->run());
        self::assertSame(1, $calls);

        $leftTask = TaskEither::left(['a', 'b'])->fold(
            static fn (array $error): string => 'left:'.count($error),
            static fn (): string => 'right',
        );

        self::assertSame('left:2', $leftTask->run());
    }

    public function testCanBeRunMoreThanOnce(): void
    {
        $calls = 0;
        $taskEither = TaskEither::defer(static function () use (&$calls): Either {
            ++$calls;

            return Either::Right(1);
        });

        self::assertTrue($taskEither->run()->isRight());
        self::assertTrue($taskEither->run()->isRight());
        self::assertSame(2, $calls);
    }

    public function testDerivedTaskEitherCanBeRunIndependently(): void
    {
        $base = TaskEither::right(1);
        $mapped = $base->map(static fn (int $value): int => $value + 1);

        self::assertSame(
            2,
            $mapped->run()->fold(
                static fn (): int => -1,
                static fn (int $value): int => $value,
            ),
        );
        self::assertSame(
            1,
            $base->run()->fold(
                static fn (): int => -1,
                static fn (int $value): int => $value,
            ),
        );
    }
}

final class CountingTransformer
{
    public int $calls = 0;

    public function __invoke(string $error): string
    {
        ++$this->calls;

        return $error;
    }
}
