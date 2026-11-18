<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Either;
use PHPUnit\Framework\TestCase;

final class EitherTest extends TestCase
{
    public function testOk(): void
    {
        $either = Either::ok(42);

        self::assertTrue($either->isOk());
        self::assertFalse($either->isFail());
    }

    public function testFail(): void
    {
        $either = Either::fail(['error']);

        self::assertFalse($either->isOk());
        self::assertTrue($either->isFail());
    }

    public function testMapTransformsOk(): void
    {
        self::assertSame(84, Either::ok(42)->map(static fn (int $value): int => $value * 2)->fold(
            static fn (int $value): int => $value,
            static fn (array $errors): int => -1,
        ));
    }

    public function testMapDoesNotRunOnFailure(): void
    {
        $called = false;

        $either = Either::fail(['error'])->map(static function (int $value) use (&$called): int {
            $called = true;

            return $value;
        });

        self::assertFalse($called);
        self::assertTrue($either->isFail());
    }

    public function testMapErrorTransformsFailure(): void
    {
        self::assertSame(
            ['wrapped'],
            Either::fail(['error'])->mapError(static fn (array $errors): array => ['wrapped'])->fold(
                static fn (): array => [],
                static fn (array $errors): array => $errors,
            ),
        );
    }

    public function testMapErrorDoesNotRunOnSuccess(): void
    {
        $called = false;

        $either = Either::ok(1)->mapError(static function (array $errors) use (&$called): array {
            $called = true;

            return $errors;
        });

        self::assertTrue($either->isOk());
        self::assertFalse($called);
    }

    public function testFlatMapChainsOnSuccess(): void
    {
        $either = Either::ok(2)->flatMap(static fn (int $value): Either => Either::ok($value * 5));

        self::assertTrue($either->isOk());
    }

    public function testFlatMapShortCircuitsOnFailure(): void
    {
        $called = false;

        $either = Either::fail(['first'])->flatMap(static function (int $value) use (&$called): Either {
            $called = true;

            return Either::ok($value);
        });

        self::assertFalse($called);
        self::assertSame(
            ['first'],
            $either->fold(static fn (): array => [], static fn (array $errors): array => $errors),
        );
    }

    public function testFoldPicksTheMatchingBranch(): void
    {
        self::assertSame(
            'ok:1',
            Either::ok(1)->fold(
                static fn (int $value): string => 'ok:'.$value,
                static fn (array $errors): string => 'fail',
            ),
        );

        self::assertSame(
            'fail:2',
            Either::fail(['a', 'b'])->fold(
                static fn (): string => 'ok',
                static fn (array $errors): string => 'fail:'.count($errors),
            ),
        );
    }
}
