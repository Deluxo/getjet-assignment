<?php

declare(strict_types=1);

namespace App\Shared\Validation;

use App\Shared\Error;
use App\Shared\Functional\TaskEither;

/**
 * @template T
 */
final readonly class Validator
{
    /**
     * @param list<ValidationRule<T>> $rules
     */
    private function __construct(private array $rules)
    {
    }

    /**
     * @template U
     *
     * @param ValidationRule<U> ...$rules
     *
     * @return self<U>
     */
    public static function loadRules(ValidationRule ...$rules): self
    {
        return new self(array_values($rules));
    }

    /**
     * @param T $subject
     *
     * @return TaskEither<list<Error>, T>
     */
    public function validate(mixed $subject): TaskEither
    {
        $chain = TaskEither::right($subject);

        foreach ($this->rules as $rule) {
            $chain = $chain->flatMap($rule->apply(...));
        }

        return $chain->mapLeft(self::asList(...));
    }

    /**
     * @return list<Error>
     */
    private static function asList(Error $error): array
    {
        return [$error];
    }
}
