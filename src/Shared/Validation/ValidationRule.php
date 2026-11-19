<?php

declare(strict_types=1);

namespace App\Shared\Validation;

use App\Shared\Error;
use App\Shared\Functional\TaskEither;

/**
 * @template T = mixed
 */
interface ValidationRule
{
    /**
     * @param T $subject
     *
     * @return TaskEither<Error, T>
     */
    public function apply(mixed $subject): TaskEither;
}
