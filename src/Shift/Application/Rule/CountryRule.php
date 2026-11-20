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
final readonly class CountryRule implements ValidationRule
{
    /**
     * @var list<string>
     */
    private const SUPPORTED = ['LT'];

    /**
     * @param CreateShiftRequest $subject
     *
     * @return TaskEither<Error, CreateShiftRequest>
     */
    public function apply(mixed $subject): TaskEither
    {
        return \in_array(strtoupper($subject->countryCode), self::SUPPORTED, true)
            ? TaskEither::right($subject)
            : TaskEither::left(Error::forField('countryCode', 'is not supported.'));
    }
}
