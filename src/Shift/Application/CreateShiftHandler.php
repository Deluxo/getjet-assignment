<?php

declare(strict_types=1);

namespace App\Shift\Application;

use App\Shift\Domain\Port\In\CreateShift;

final class CreateShiftHandler implements CreateShift
{
    public function create(string $employeeId, string $date, string $startTime, string $endTime, string $countryCode): string
    {
        throw new \LogicException('CreateShiftHandler is not implemented yet.');
    }
}
