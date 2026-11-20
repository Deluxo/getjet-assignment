<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Application;

use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Shift\Application\CreateShiftHandler;
use App\Shift\Application\Rule\CountryRule;
use App\Shift\Application\Rule\EmployeeIdRule;
use App\Shift\Application\Rule\HolidayRule;
use App\Shift\Application\Rule\ShiftLengthRule;
use App\Shift\Application\Rule\ShiftWithinSameDayRule;
use App\Shift\Domain\Port\In\CreateShiftRequest;
use App\Tests\Unit\Shift\Application\Support\FakeHolidayCalendar;
use PHPUnit\Framework\TestCase;

final class CreateShiftHandlerTest extends TestCase
{
    public function testRejectsTheShiftWhenTheDateIsAPublicHoliday(): void
    {
        $result = self::handler(new FakeHolidayCalendar(true))->invoke(self::request())->run();

        self::assertEquals(
            [Error::forField('date', 'is a public holiday.')],
            self::left($result),
        );
    }

    public function testReturnsTheValidatedRequestWhenTheDateIsNotAHoliday(): void
    {
        $request = self::request();

        $result = self::handler(new FakeHolidayCalendar(false))->invoke($request)->run();

        self::assertSame($request, self::right($result));
    }

    public function testPassesCalendarErrorsThroughAsAList(): void
    {
        $error = new Error('holiday_calendar', 'The public holiday calendar is unavailable.');

        $result = self::handler(new FakeHolidayCalendar($error))->invoke(self::request())->run();

        self::assertSame([$error], self::left($result));
    }

    public function testStopsAtTheFirstFailingRuleWithoutConsultingTheCalendar(): void
    {
        $calendar = new FakeHolidayCalendar(false);

        $result = self::handler($calendar)->invoke(self::request(countryCode: 'EE'))->run();

        self::assertEquals(
            [Error::forField('countryCode', 'is not supported.')],
            self::left($result),
        );
        self::assertSame(0, $calendar->calls);
    }

    public function testRejectsAnInvalidTimeBeforeConsultingTheCalendar(): void
    {
        $calendar = new FakeHolidayCalendar(false);

        $result = self::handler($calendar)->invoke(self::request(startTime: '9:00'))->run();

        self::assertEquals(
            [Error::forField('startTime', 'is not a valid time.')],
            self::left($result),
        );
        self::assertSame(0, $calendar->calls);
    }

    private static function handler(FakeHolidayCalendar $calendar): CreateShiftHandler
    {
        return new CreateShiftHandler(
            new EmployeeIdRule(),
            new CountryRule(),
            new ShiftWithinSameDayRule(),
            new ShiftLengthRule(),
            new HolidayRule($calendar),
        );
    }

    private static function request(
        string $employeeId = 'employee-123',
        string $date = '2026-10-06',
        string $startTime = '09:00',
        string $endTime = '17:00',
        string $countryCode = 'LT',
    ): CreateShiftRequest {
        return new CreateShiftRequest(
            employeeId: $employeeId,
            date: $date,
            startTime: $startTime,
            endTime: $endTime,
            countryCode: $countryCode,
        );
    }

    /**
     * @template T
     *
     * @param Either<list<Error>, T> $either
     *
     * @return list<Error>
     */
    private static function left(Either $either): mixed
    {
        return $either->fold(
            static fn (mixed $errors): mixed => $errors,
            static fn (): never => throw new \LogicException('Expected a left value.'),
        );
    }

    /**
     * @template T
     *
     * @param Either<list<Error>, T> $either
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
