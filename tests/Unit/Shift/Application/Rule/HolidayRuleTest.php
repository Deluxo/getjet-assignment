<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Application\Rule;

use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Shift\Application\Rule\HolidayRule;
use App\Shift\Domain\Port\In\CreateShiftRequest;
use App\Tests\Unit\Shift\Application\Support\FakeHolidayCalendar;
use PHPUnit\Framework\TestCase;

final class HolidayRuleTest extends TestCase
{
    public function testPassesTheRequestThroughWhenTheDateIsNotAHoliday(): void
    {
        $request = self::request();
        $calendar = new FakeHolidayCalendar(false);

        $result = (new HolidayRule($calendar))->apply($request)->run();

        self::assertSame($request, self::right($result));
        self::assertSame(1, $calendar->calls);
    }

    public function testRejectsTheRequestWhenTheDateIsAPublicHoliday(): void
    {
        $result = (new HolidayRule(new FakeHolidayCalendar(true)))->apply(self::request())->run();

        self::assertEquals(
            Error::forField('date', 'is a public holiday.'),
            self::left($result),
        );
    }

    public function testPassesCalendarErrorsThrough(): void
    {
        $error = new Error('holiday_calendar', 'The public holiday calendar is unavailable.');

        $result = (new HolidayRule(new FakeHolidayCalendar($error)))->apply(self::request())->run();

        self::assertSame($error, self::left($result));
    }

    public function testRejectsAnUnparseableDateWithoutConsultingTheCalendar(): void
    {
        $calendar = new FakeHolidayCalendar(false);

        $result = (new HolidayRule($calendar))->apply(self::request('2026-02-30'))->run();

        self::assertEquals(
            Error::forField('date', 'is not a valid date.'),
            self::left($result),
        );
        self::assertSame(0, $calendar->calls);
    }

    private static function request(string $date = '2026-10-06'): CreateShiftRequest
    {
        return new CreateShiftRequest(
            employeeId: 'employee-123',
            date: $date,
            startTime: '09:00',
            endTime: '17:00',
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
