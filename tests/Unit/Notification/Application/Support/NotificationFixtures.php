<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Application\Support;

use App\Notification\Domain\ShiftNotification;
use App\Shift\Domain\Port\In\CreateShiftRequest;
use App\Shift\Domain\Port\Out\Shift;

trait NotificationFixtures
{
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
            self::fixtureDate($date),
            self::fixtureTime($startTime),
            self::fixtureTime($endTime),
        );
    }

    private static function shift(): Shift
    {
        return Shift::create(
            'employee-1',
            self::fixtureDate('2026-10-06'),
            self::fixtureTime('09:00'),
            self::fixtureTime('17:00'),
            'LT',
        );
    }

    private static function fixtureDate(string $value): \DateTimeImmutable
    {
        $parsed = CreateShiftRequest::parseDate($value);

        if (null === $parsed) {
            throw new \LogicException(sprintf('Cannot parse "%s".', $value));
        }

        return $parsed;
    }

    private static function fixtureTime(string $value): \DateTimeImmutable
    {
        $parsed = CreateShiftRequest::parseTime($value);

        if (null === $parsed) {
            throw new \LogicException(sprintf('Cannot parse "%s".', $value));
        }

        return $parsed;
    }
}
