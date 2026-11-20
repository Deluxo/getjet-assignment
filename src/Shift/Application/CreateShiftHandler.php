<?php

declare(strict_types=1);

namespace App\Shift\Application;

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
use App\Shift\Domain\Port\Out\Shift;
use App\Shift\Domain\Port\Out\ShiftRepository;

final class CreateShiftHandler implements CreateShift
{
    public function __construct(
        private readonly EmployeeIdRule $employeeIdRule,
        private readonly CountryRule $countryRule,
        private readonly ShiftWithinSameDayRule $withinSameDayRule,
        private readonly ShiftLengthRule $lengthRule,
        private readonly HolidayRule $holidayRule,
        private readonly ShiftRepository $shifts,
    ) {}

    /**
     * @return TaskEither<list<Error>, Shift>
     */
    public function invoke(CreateShiftRequest $request): TaskEither
    {
        return Validator::loadRules(
            $this->employeeIdRule,
            $this->countryRule,
            $this->withinSameDayRule,
            $this->lengthRule,
            $this->holidayRule,
        )
            ->validate($request)
            ->flatMap($this->persist(...));
    }

    /**
     * @return TaskEither<list<Error>, Shift>
     */
    private function persist(CreateShiftRequest $request): TaskEither
    {
        $shift = Shift::create(
            $request->employeeId,
            $request->date,
            $request->startTime,
            $request->endTime,
            $request->countryCode,
        );

        return $this->shifts
            ->create($shift)
            ->mapLeft(self::asList(...));
    }

    /**
     * @return list<Error>
     */
    private static function asList(Error $error): array
    {
        return [$error];
    }
}
