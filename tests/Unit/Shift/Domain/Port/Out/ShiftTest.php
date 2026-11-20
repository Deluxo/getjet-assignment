<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Domain\Port\Out;

use App\Shift\Domain\Port\In\CreateShiftRequest;
use App\Shift\Domain\Port\Out\Shift;
use PHPUnit\Framework\TestCase;

final class ShiftTest extends TestCase
{
    public function testCreateAssignsAUuidV7String(): void
    {
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            self::shift()->id,
        );
    }

    public function testCreateNormalizesTheCountryCode(): void
    {
        self::assertSame('LT', self::shift(countryCode: 'lt')->countryCode);
    }

    public function testSerializesTheShiftWithTheApiFieldNames(): void
    {
        self::assertSame(
            [
                'id' => 'shift-id',
                'employee_id' => 'employee-123',
                'date' => '2026-10-06',
                'start_time' => '09:00',
                'end_time' => '17:00',
                'country_code' => 'LT',
            ],
            self::shift(id: 'shift-id')->toJson(),
        );
    }

    private static function shift(
        string $id = 'ignored',
        string $countryCode = 'LT',
    ): Shift {
        $shift = Shift::create(
            'employee-123',
            self::date('2026-10-06'),
            self::date('09:00', 'H:i'),
            self::date('17:00', 'H:i'),
            $countryCode,
        );

        return 'ignored' === $id ? $shift : new Shift(
            $id,
            $shift->employeeId,
            $shift->date,
            $shift->startTime,
            $shift->endTime,
            $shift->countryCode,
        );
    }

    private static function date(string $value, string $format = 'Y-m-d'): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);

        if (false === $date) {
            throw new \LogicException(sprintf('Cannot parse "%s".', $value));
        }

        return $date;
    }
}
