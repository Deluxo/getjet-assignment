<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Application\Rule;

use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Shift\Application\Rule\CountryRule;
use App\Shift\Domain\Port\In\CreateShiftRequest;
use PHPUnit\Framework\TestCase;

final class CountryRuleTest extends TestCase
{
    public function testPassesLithuania(): void
    {
        $request = self::request('LT');

        self::assertSame($request, self::right((new CountryRule())->apply($request)->run()));
    }

    public function testPassesLithuaniaRegardlessOfCase(): void
    {
        $request = self::request('lt');

        self::assertSame($request, self::right((new CountryRule())->apply($request)->run()));
    }

    public function testRejectsAnUnsupportedCountry(): void
    {
        $result = (new CountryRule())->apply(self::request('EE'))->run();

        self::assertEquals(
            Error::forField('countryCode', 'is not supported.'),
            self::left($result),
        );
    }

    private static function request(string $countryCode): CreateShiftRequest
    {
        return new CreateShiftRequest(
            employeeId: 'employee-123',
            date: '2026-10-06',
            startTime: '09:00',
            endTime: '17:00',
            countryCode: $countryCode,
        );
    }

    /**
     * @template T
     *
     * @param Either<Error, T> $either
     *
     * @return Error
     */
    private static function left(Either $either): mixed
    {
        return $either->fold(
            static fn (mixed $error): mixed => $error,
            static fn (): never => throw new \LogicException('Expected a left value.'),
        );
    }

    /**
     * @template T
     *
     * @param Either<Error, T> $either
     *
     * @return T
     */
    private static function right(Either $either): mixed
    {
        return $either->fold(
            static fn (): never => throw new \LogicException('Expected a right value.'),
            static fn (mixed $value): mixed => $value,
        );
    }
}
