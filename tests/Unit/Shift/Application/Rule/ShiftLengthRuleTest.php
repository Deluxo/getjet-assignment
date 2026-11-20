<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Application\Rule;

use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Shift\Application\Rule\ShiftLengthRule;
use App\Shift\Domain\Port\In\CreateShiftRequest;
use PHPUnit\Framework\TestCase;

final class ShiftLengthRuleTest extends TestCase
{
    public function testPassesAShiftShorterThanTwelveHours(): void
    {
        $request = self::request('09:00', '17:00');

        self::assertSame($request, self::right((new ShiftLengthRule())->apply($request)->run()));
    }

    public function testPassesAShiftOfExactlyTwelveHours(): void
    {
        $request = self::request('09:00', '21:00');

        self::assertSame($request, self::right((new ShiftLengthRule())->apply($request)->run()));
    }

    public function testRejectsAShiftLongerThanTwelveHours(): void
    {
        $result = (new ShiftLengthRule())->apply(self::request('09:00', '21:01'))->run();

        self::assertEquals(
            Error::forField('endTime', 'must not exceed 12 hours.'),
            self::left($result),
        );
    }

    public function testRejectsAnInvalidStartTime(): void
    {
        $result = (new ShiftLengthRule())->apply(self::request('9:00', '17:00'))->run();

        self::assertEquals(
            Error::forField('startTime', 'is not a valid time.'),
            self::left($result),
        );
    }

    public function testRejectsAnInvalidEndTime(): void
    {
        $result = (new ShiftLengthRule())->apply(self::request('09:00', 'nope'))->run();

        self::assertEquals(
            Error::forField('endTime', 'is not a valid time.'),
            self::left($result),
        );
    }

    private static function request(string $startTime, string $endTime): CreateShiftRequest
    {
        return new CreateShiftRequest(
            employeeId: 'employee-123',
            date: '2026-10-06',
            startTime: $startTime,
            endTime: $endTime,
            countryCode: 'LT',
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
