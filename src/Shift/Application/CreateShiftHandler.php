<?php

declare(strict_types=1);

namespace App\Shift\Application;

use App\Shared\Error;
use App\Shared\Functional\TaskEither;
use App\Shared\Validation\Validator;
use App\Shift\Application\Rule\HolidayRule;
use App\Shift\Domain\Port\In\CreateShift;
use App\Shift\Domain\Port\In\CreateShiftRequest;

final class CreateShiftHandler implements CreateShift
{
    public function __construct(
        private readonly HolidayRule $holidayRule,
    ) {}

    /**
     * @return TaskEither<list<Error>, CreateShiftRequest>
     */
    public function invoke(CreateShiftRequest $request): TaskEither
    {
        return TaskEither::right($request)
            ->flatMap(fn(CreateShiftRequest $request): TaskEither => Validator::loadRules(
                $this->holidayRule,
            )->validate($request));
    }
}
