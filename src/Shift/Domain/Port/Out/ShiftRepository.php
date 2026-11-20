<?php

declare(strict_types=1);

namespace App\Shift\Domain\Port\Out;

use App\Shared\Error;
use App\Shared\Functional\TaskEither;

interface ShiftRepository
{
    /**
     * Stores the shift unless it overlaps another shift of the same employee
     * on the same day. The overlap check and the insert are one atomic
     * operation, so concurrent requests cannot both pass the check.
     *
     * @return TaskEither<Error, Shift> Left: overlapping or storage failure.
     */
    public function create(Shift $shift): TaskEither;
}
