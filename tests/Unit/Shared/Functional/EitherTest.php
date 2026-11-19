<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Functional;

use App\Shared\Functional\Either;
use PHPUnit\Framework\TestCase;

final class EitherTest extends TestCase
{
    public function testRight(): void
    {
        $either = Either::Right(42);

        self::assertTrue($either->isRight());
        self::assertFalse($either->isLeft());
    }

    public function testLeft(): void
    {
        $either = Either::Left(['error']);

        self::assertFalse($either->isRight());
        self::assertTrue($either->isLeft());
    }

    public function testOfIsEquivalentToRight(): void
    {
        self::assertTrue(Either::of(42)->isRight());
    }

    public function testMapTransformsRight(): void
    {
        self::assertSame(84, Either::Right(42)->map(static fn (int $value): int => $value * 2)->fold(
            static fn (array $errors): int => -1,
            static fn (int $value): int => $value,
        ));
    }

    public function testMapDoesNotRunOnFailure(): void
    {
        $called = false;

        $either = Either::Left(['error'])->map(static function (int $value) use (&$called): int {
            $called = true;

            return $value;
        });

        self::assertFalse($called);
        self::assertTrue($either->isLeft());
    }

    public function testFlatMapChainsOnSuccess(): void
    {
        $either = Either::Right(2)->flatMap(static fn (int $value): Either => Either::Right($value * 5));

        self::assertTrue($either->isRight());
    }

    public function testFlatMapShortCircuitsOnFailure(): void
    {
        $called = false;

        $either = Either::Left(['first'])->flatMap(static function (int $value) use (&$called): Either {
            $called = true;

            return Either::Right($value);
        });

        self::assertFalse($called);
        self::assertSame(
            ['first'],
            $either->fold(static fn (array $errors): array => $errors, static fn (): array => []),
        );
    }

    public function testFoldPicksTheMatchingBranch(): void
    {
        self::assertSame(
            'right:1',
            Either::Right(1)->fold(
                static fn (array $errors): string => 'left',
                static fn (int $value): string => 'right:'.$value,
            ),
        );

        self::assertSame(
            'left:2',
            Either::Left(['a', 'b'])->fold(
                static fn (array $errors): string => 'left:'.count($errors),
                static fn (): string => 'right',
            ),
        );
    }
}
