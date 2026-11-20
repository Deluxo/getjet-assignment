<?php

declare(strict_types=1);

namespace App\Shift\Domain\Port\In;

/**
 * A shift request that passed every validation rule and whose date and times
 * are parsed. Persisting code only ever sees this type.
 */
final readonly class CreateShiftCommand
{
    public function __construct(
        public string $employeeId,
        public \DateTimeImmutable $date,
        public \DateTimeImmutable $startTime,
        public \DateTimeImmutable $endTime,
        public string $countryCode,
    ) {
    }
}
