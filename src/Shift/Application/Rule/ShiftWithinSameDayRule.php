<?php

declare(strict_types=1);

namespace App\Shift\Application\Rule;

use App\Shared\Error;
use App\Shared\Functional\TaskEither;
use App\Shared\Validation\ValidationRule;
use App\Shift\Domain\Port\In\CreateShiftRequest;

/**
 * @implements ValidationRule<CreateShiftRequest>
 */
final readonly class ShiftWithinSameDayRule implements ValidationRule
{
    /**
     * @param CreateShiftRequest $subject
     *
     * @return TaskEither<Error, CreateShiftRequest>
     */
    public function apply(mixed $subject): TaskEither
    {
        $start = $subject->startTime;
        $end = $subject->endTime;

        if (null === $start) {
            return TaskEither::left(Error::forField('startTime', 'is not a valid time.'));
        }

        if (null === $end) {
            return TaskEither::left(Error::forField('endTime', 'is not a valid time.'));
        }

        return $end > $start
            ? TaskEither::right($subject)
            : TaskEither::left(Error::forField('endTime', 'must be after the start time.'));
    }
}
