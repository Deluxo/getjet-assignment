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
final readonly class EmployeeIdRule implements ValidationRule
{
    /**
     * @param CreateShiftRequest $subject
     *
     * @return TaskEither<Error, CreateShiftRequest>
     */
    public function apply(mixed $subject): TaskEither
    {
        return '' === trim($subject->employeeId)
            ? TaskEither::left(Error::forField('employeeId', 'must not be empty.'))
            : TaskEither::right($subject);
    }
}
