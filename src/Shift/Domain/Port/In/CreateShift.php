<?php

declare(strict_types=1);

namespace App\Shift\Domain\Port\In;

use App\Shared\Error;
use App\Shared\Functional\TaskEither;
use App\Shift\Domain\Port\Out\Shift;

interface CreateShift
{
    /**
     * @return TaskEither<list<Error>, Shift>
     */
    public function invoke(CreateShiftRequest $request): TaskEither;
}
