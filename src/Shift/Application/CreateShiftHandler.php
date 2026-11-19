<?php

declare(strict_types=1);

namespace App\Shift\Application;

use App\Shared\Error;
use App\Shared\Functional\TaskEither;
use App\Shift\Domain\Port\In\CreateShift;
use App\Shift\Domain\Port\In\CreateShiftRequest;
use App\Shift\Domain\Port\Out\CreatedShift;

final class CreateShiftHandler implements CreateShift
{
    /**
     * @return TaskEither<list<Error>, CreatedShift>
     */
    public function invoke(CreateShiftRequest $request): TaskEither
    {

        var_dump('labas');

        return TaskEither::of(1);
    }
}
