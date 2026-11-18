<?php

declare(strict_types=1);

namespace App\Shift\Application\Port;

interface CreateShift
{
    public function create(string $employeeId, string $date, string $startTime, string $endTime, string $countryCode): string;
}
