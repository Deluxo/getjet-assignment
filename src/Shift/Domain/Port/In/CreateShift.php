<?php

declare(strict_types=1);

namespace App\Shift\Domain\Port\In;

use App\Shared\Functional\Task;
use App\Shift\Domain\Port\Out\CreatedShift;
use App\Shift\Domain\Port\Out\ShiftErrors;

interface CreateShift
{
    /**
     * @return Task<Either<ShiftErrors, CreatedShift>>
     */
    public function create(string $employeeId, string $date, string $startTime, string $endTime, string $countryCode): Task;
}
