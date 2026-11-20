<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Domain;

use App\Notification\Domain\ShiftNotification;
use App\Shift\Domain\Port\In\CreateShiftRequest;
use App\Shift\Domain\Port\Out\Shift;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ShiftNotificationTest extends TestCase
{
    public function testIdentityIsTheHashOfTheNotificationContent(): void
    {
        self::assertSame(
            hash('sha256', 'shift-notification:v1|shift-1|employee-1|2026-10-06|09:00|17:00'),
            self::notification()->id(),
        );
    }

    public function testIdentityIsStableForEqualContent(): void
    {
        self::assertSame(self::notification()->id(), self::notification()->id());
    }

    #[DataProvider('changedContent')]
    public function testIdentityChangesWithTheContent(string $expected, ShiftNotification $notification): void
    {
        self::assertNotSame(self::notification()->id(), $notification->id());
        self::assertSame($expected, $notification->id());
    }

    /**
     * @return iterable<string, array{string, ShiftNotification}>
     */
    public static function changedContent(): iterable
    {
        yield 'another shift' => [
            hash('sha256', 'shift-notification:v1|shift-2|employee-1|2026-10-06|09:00|17:00'),
            self::notification(shiftId: 'shift-2'),
        ];
        yield 'another employee' => [
            hash('sha256', 'shift-notification:v1|shift-1|employee-2|2026-10-06|09:00|17:00'),
            self::notification(employeeId: 'employee-2'),
        ];
        yield 'another date' => [
            hash('sha256', 'shift-notification:v1|shift-1|employee-1|2026-10-07|09:00|17:00'),
            self::notification(date: '2026-10-07'),
        ];
        yield 'another start time' => [
            hash('sha256', 'shift-notification:v1|shift-1|employee-1|2026-10-06|10:00|17:00'),
            self::notification(startTime: '10:00'),
        ];
        yield 'another end time' => [
            hash('sha256', 'shift-notification:v1|shift-1|employee-1|2026-10-06|09:00|18:00'),
            self::notification(endTime: '18:00'),
        ];
    }

    public function testIsBuiltFromAStoredShift(): void
    {
        $shift = Shift::create(
            'employee-123',
            self::date('2026-10-06'),
            self::time('09:00'),
            self::time('17:00'),
            'LT',
        );

        $notification = ShiftNotification::forShift($shift);

        self::assertSame($shift->id, $notification->shiftId);
        self::assertSame('employee-123', $notification->employeeId);
        self::assertSame('2026-10-06', $notification->date->format('Y-m-d'));
        self::assertSame('09:00', $notification->startTime->format('H:i'));
        self::assertSame('17:00', $notification->endTime->format('H:i'));
        self::assertSame($notification->id(), $notification->toJson()['id']);
    }

    public function testSerializesTheNotificationWithApiFieldNames(): void
    {
        self::assertSame(
            [
                'id' => self::notification()->id(),
                'shift_id' => 'shift-1',
                'employee_id' => 'employee-1',
                'date' => '2026-10-06',
                'start_time' => '09:00',
                'end_time' => '17:00',
            ],
            self::notification()->toJson(),
        );
    }

    private static function notification(
        string $shiftId = 'shift-1',
        string $employeeId = 'employee-1',
        string $date = '2026-10-06',
        string $startTime = '09:00',
        string $endTime = '17:00',
    ): ShiftNotification {
        return new ShiftNotification(
            $shiftId,
            $employeeId,
            self::date($date),
            self::time($startTime),
            self::time($endTime),
        );
    }

    private static function date(string $value): \DateTimeImmutable
    {
        $parsed = CreateShiftRequest::parseDate($value);

        if (null === $parsed) {
            throw new \LogicException(sprintf('Cannot parse "%s".', $value));
        }

        return $parsed;
    }

    private static function time(string $value): \DateTimeImmutable
    {
        $parsed = CreateShiftRequest::parseTime($value);

        if (null === $parsed) {
            throw new \LogicException(sprintf('Cannot parse "%s".', $value));
        }

        return $parsed;
    }
}
