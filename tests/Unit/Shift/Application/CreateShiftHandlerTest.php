<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Application;

use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Shift\Application\CreateShiftHandler;
use App\Shift\Application\Rule\HolidayRule;
use App\Shift\Domain\Port\In\CreateShiftRequest;
use App\Tests\Unit\Shift\Application\Support\FakeHolidayCalendar;
use PHPUnit\Framework\TestCase;

final class CreateShiftHandlerTest extends TestCase
{
    public function testRejectsTheShiftWhenTheDateIsAPublicHoliday(): void
    {
        $handler = new CreateShiftHandler(new HolidayRule(new FakeHolidayCalendar(true)));

        $result = $handler->invoke(self::request())->run();

        self::assertEquals(
            [Error::forField('date', 'is a public holiday.')],
            self::left($result),
        );
    }

    public function testReturnsTheValidatedRequestWhenTheDateIsNotAHoliday(): void
    {
        $request = self::request();
        $handler = new CreateShiftHandler(new HolidayRule(new FakeHolidayCalendar(false)));

        $result = $handler->invoke($request)->run();

        self::assertSame($request, self::right($result));
    }

    public function testPassesCalendarErrorsThroughAsAList(): void
    {
        $error = new Error('holiday_calendar', 'The public holiday calendar is unavailable.');
        $handler = new CreateShiftHandler(new HolidayRule(new FakeHolidayCalendar($error)));

        $result = $handler->invoke(self::request())->run();

        self::assertSame([$error], self::left($result));
    }

    private static function request(): CreateShiftRequest
    {
        return new CreateShiftRequest(
            employeeId: 'employee-123',
            date: '2026-10-06',
            startTime: '09:00',
            endTime: '17:00',
            countryCode: 'LT',
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
