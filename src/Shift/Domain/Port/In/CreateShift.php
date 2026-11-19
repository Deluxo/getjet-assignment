<?php

declare(strict_types=1);

namespace App\Shift\Domain\Port\In;

use App\Shared\Functional\TaskEither;
use App\Shift\Domain\Port\Out\CreatedShift;
use App\Shift\Domain\Port\Out\ShiftErrors;

interface CreateShift
{
    /**
     * @return TaskEither<ShiftErrors, CreatedShift>
     */
    public function invoke(CreateShiftRequest $request): TaskEither;
}
