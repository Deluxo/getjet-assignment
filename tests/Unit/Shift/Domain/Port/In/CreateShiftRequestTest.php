<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Domain\Port\In;

use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Shift\Domain\Port\In\CreateShiftCommand;
use App\Shift\Domain\Port\In\CreateShiftRequest;
use PHPUnit\Framework\TestCase;

final class CreateShiftRequestTest extends TestCase
{
    public function testParsesTheDateIntoADateTimeImmutable(): void
    {
        $request = self::request(date: '2026-10-06');

        self::assertSame('2026-10-06', $request->date?->format('Y-m-d'));
    }

    public function testParsesTheTimesIntoDateTimeImmutable(): void
    {
        $request = self::request(startTime: '09:00', endTime: '17:30');

        self::assertSame('09:00', $request->startTime?->format('H:i'));
        self::assertSame('17:30', $request->endTime?->format('H:i'));
    }

    public function testLeavesTheDateNullWhenItDoesNotExist(): void
    {
        self::assertNull(self::request(date: '2026-02-30')->date);
    }

    public function testLeavesTheDateNullWhenItIsNotZeroPadded(): void
    {
        self::assertNull(self::request(date: '2026-1-6')->date);
    }

    public function testLeavesTheDateNullWhenItCannotBeParsed(): void
    {
        self::assertNull(self::request(date: 'not-a-date')->date);
    }

    public function testLeavesTheStartTimeNullWhenItIsNotZeroPadded(): void
    {
        self::assertNull(self::request(startTime: '9:00')->startTime);
    }

    public function testLeavesTheEndTimeNullWhenItCannotBeParsed(): void
    {
        self::assertNull(self::request(endTime: 'nope')->endTime);
    }

    public function testKeepsTheOtherFieldsAsGiven(): void
    {
        $request = self::request();

        self::assertSame('employee-123', $request->employeeId);
        self::assertSame('LT', $request->countryCode);
    }

    public function testPromotesAValidRequestIntoACommand(): void
    {
        $command = self::right(self::request()->toCommand());

        self::assertInstanceOf(CreateShiftCommand::class, $command);
        self::assertSame('employee-123', $command->employeeId);
        self::assertSame('2026-10-06', $command->date->format('Y-m-d'));
        self::assertSame('09:00', $command->startTime->format('H:i'));
        self::assertSame('17:00', $command->endTime->format('H:i'));
        self::assertSame('LT', $command->countryCode);
    }

    public function testRefusesToPromoteAnUnparseableDate(): void
    {
        self::assertEquals(
            Error::forField('date', 'is not a valid date.'),
            self::left(self::request(date: 'not-a-date')->toCommand()),
        );
    }

    public function testRefusesToPromoteAnUnparseableStartTime(): void
    {
        self::assertEquals(
            Error::forField('startTime', 'is not a valid time.'),
            self::left(self::request(startTime: '9:00')->toCommand()),
        );
    }

    public function testRefusesToPromoteAnUnparseableEndTime(): void
    {
        self::assertEquals(
            Error::forField('endTime', 'is not a valid time.'),
            self::left(self::request(endTime: 'nope')->toCommand()),
        );
    }

    private static function request(
        string $date = '2026-10-06',
        string $startTime = '09:00',
        string $endTime = '17:00',
    ): CreateShiftRequest {
        return new CreateShiftRequest(
            employeeId: 'employee-123',
            date: $date,
            startTime: $startTime,
            endTime: $endTime,
            countryCode: 'LT',
        );
    }

    /**
     * @template T
     *
     * @param Either<Error, T> $either
     */
    private static function left(Either $either): mixed
    {
        return $either->fold(
            static fn (mixed $errors): mixed => $errors,
            static fn (): never => throw new \LogicException('Expected a left value.'),
        );
    }

    /**
     * @return mixed
     */
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
