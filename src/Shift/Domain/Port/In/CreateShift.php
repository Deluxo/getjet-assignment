<?php

declare(strict_types=1);

namespace App\Shift\Domain\Port\In;

use App\Shared\Functional\Task;

interface CreateShift
{
    /**
     * @return Task<Either<list<Error>, string>>
     */
    public function create(string $employeeId, string $date, string $startTime, string $endTime, string $countryCode): Task;
}
