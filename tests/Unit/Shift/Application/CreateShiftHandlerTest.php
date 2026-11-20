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
use App\Shift\Domain\Port\Out\Shift;
use App\Tests\Unit\Shift\Application\Support\FakeHolidayCalendar;
use App\Tests\Unit\Shift\Application\Support\FakeShiftEvents;
use App\Tests\Unit\Shift\Application\Support\FakeShiftRepository;
use PHPUnit\Framework\TestCase;

final class CreateShiftHandlerTest extends TestCase
{
    public function testRejectsTheShiftWhenTheDateIsAPublicHoliday(): void
    {
        $shifts = new FakeShiftRepository();

        $result = self::handler(new FakeHolidayCalendar(true), $shifts)->invoke(self::request())->run();

        self::assertEquals(
            [Error::forField('date', 'is a public holiday.')],
            self::left($result),
        );
        self::assertSame(0, $shifts->createCalls);
    }

    public function testReturnsTheStoredShiftWhenTheRequestIsValid(): void
    {
        $shifts = new FakeShiftRepository();
        $events = new FakeShiftEvents();

        $result = self::handler(new FakeHolidayCalendar(false), $shifts, $events)
            ->invoke(self::request())
            ->run();

        $shift = self::right($result);

        self::assertInstanceOf(Shift::class, $shift);
        self::assertSame($shifts->saved[0], $shift);
        self::assertSame('employee-123', $shift->employeeId);
        self::assertSame('LT', $shift->countryCode);
        self::assertSame(1, $shifts->createCalls);
        self::assertSame([$shift], $events->published);
    }

    public function testDoesNotPublishWhenTheShiftIsRejected(): void
    {
        $events = new FakeShiftEvents();

        $result = self::handler(
            new FakeHolidayCalendar(true),
            new FakeShiftRepository(),
            $events,
        )->invoke(self::request())->run();

        self::assertTrue($result->isLeft());
        self::assertSame([], $events->published);
    }

    public function testDoesNotPublishWhenTheShiftCannotBeStored(): void
    {
        $events = new FakeShiftEvents();

        $result = self::handler(
            new FakeHolidayCalendar(false),
            new FakeShiftRepository(new Error('shift_repository', 'The shift could not be saved.')),
            $events,
        )->invoke(self::request())->run();

        self::assertTrue($result->isLeft());
        self::assertSame([], $events->published);
    }

    public function testPassesCalendarErrorsThroughAsAList(): void
    {
        $error = new Error('holiday_calendar', 'The public holiday calendar is unavailable.');
        $shifts = new FakeShiftRepository();

        $result = self::handler(new FakeHolidayCalendar($error), $shifts)
            ->invoke(self::request())
            ->run();

        self::assertSame([$error], self::left($result));
        self::assertSame(0, $shifts->createCalls);
    }

    public function testPassesRepositoryErrorsThroughAsAList(): void
    {
        $error = new Error('shift_repository', 'The shift could not be saved.');

        $result = self::handler(
            new FakeHolidayCalendar(false),
            new FakeShiftRepository($error),
        )->invoke(self::request())->run();

        self::assertSame([$error], self::left($result));
    }

    public function testStopsAtTheFirstFailingRuleWithoutConsultingTheCalendar(): void
    {
        $calendar = new FakeHolidayCalendar(false);
        $shifts = new FakeShiftRepository();

        $result = self::handler($calendar, $shifts)->invoke(self::request(countryCode: 'EE'))->run();

        self::assertEquals(
            [Error::forField('countryCode', 'is not supported.')],
            self::left($result),
        );
        self::assertSame(0, $calendar->calls);
        self::assertSame(0, $shifts->createCalls);
    }

    public function testRejectsAnInvalidTimeBeforeConsultingTheCalendar(): void
    {
        $calendar = new FakeHolidayCalendar(false);
        $shifts = new FakeShiftRepository();

        $result = self::handler($calendar, $shifts)->invoke(self::request(startTime: '9:00'))->run();

        self::assertEquals(
            [Error::forField('startTime', 'is not a valid time.')],
            self::left($result),
        );
        self::assertSame(0, $calendar->calls);
        self::assertSame(0, $shifts->createCalls);
    }

    public function testRejectsAnOverlappingShiftWithoutStoringIt(): void
    {
        $shifts = new FakeShiftRepository(
            Error::forField('startTime', 'overlaps another shift for this employee.'),
        );

        $result = self::handler(new FakeHolidayCalendar(false), $shifts)
            ->invoke(self::request())
            ->run();

        self::assertEquals(
            [Error::forField('startTime', 'overlaps another shift for this employee.')],
            self::left($result),
        );
        self::assertSame([], $shifts->saved);
    }

    private static function handler(
        FakeHolidayCalendar $calendar,
        ?FakeShiftRepository $shifts = null,
        ?FakeShiftEvents $events = null,
    ): CreateShiftHandler {
        return new CreateShiftHandler(
            new EmployeeIdRule(),
            new CountryRule(),
            new ShiftWithinSameDayRule(),
            new ShiftLengthRule(),
            new HolidayRule($calendar),
            $shifts ?? new FakeShiftRepository(),
            $events ?? new FakeShiftEvents(),
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
            static fn(mixed $errors): mixed => $errors,
            static fn(): never => throw new \LogicException('Expected a left value.'),
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
            static fn(): never => throw new \LogicException('Expected a right value.'),
            static fn(mixed $value): mixed => $value,
        );
    }
}
