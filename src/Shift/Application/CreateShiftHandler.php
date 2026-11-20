<?php

declare(strict_types=1);

namespace App\Shift\Application;

use App\Shared\Debug;
use App\Shared\Error;
use App\Shared\Functional\TaskEither;
use App\Shared\Validation\Validator;
use App\Shift\Application\Rule\CountryRule;
use App\Shift\Application\Rule\EmployeeIdRule;
use App\Shift\Application\Rule\HolidayRule;
use App\Shift\Application\Rule\ShiftLengthRule;
use App\Shift\Application\Rule\ShiftWithinSameDayRule;
use App\Shift\Domain\Port\In\CreateShift;
use App\Shift\Domain\Port\In\CreateShiftRequest;

final class CreateShiftHandler implements CreateShift
{
    public function __construct(
        private readonly EmployeeIdRule $employeeIdRule,
        private readonly CountryRule $countryRule,
        private readonly ShiftWithinSameDayRule $withinSameDayRule,
        private readonly ShiftLengthRule $lengthRule,
        private readonly HolidayRule $holidayRule,
    ) {}

    /**
     * @return TaskEither<list<Error>, CreateShiftRequest>
     */
    public function invoke(CreateShiftRequest $request): TaskEither
    {
        return $request
        |> Debug::dump(...)
        |> Validator::loadRules(
            $this->employeeIdRule,
            $this->countryRule,
            $this->withinSameDayRule,
            $this->lengthRule,
            $this->holidayRule,
        )->validate(...)
        |> (fn($a) => $a->run())
        |> Debug::dump(...);
    }
}
