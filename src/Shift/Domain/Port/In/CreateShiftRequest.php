<?php

declare(strict_types=1);

namespace App\Shift\Domain\Port\In;

final readonly class CreateShiftRequest
{
    public function __construct(
        public string $employeeId,
        public string $date,
        public string $startTime,
        public string $endTime,
        public string $countryCode,
    ) {
    }
}
