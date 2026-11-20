<?php

declare(strict_types=1);

namespace App\Shared\Validation;

use App\Shared\Error;
use App\Shared\Functional\Either;
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
        return $this->chain($subject)->mapLeft(self::asList(...));
    }

    /**
     * Validates the subject and, when every rule passes, promotes it to the
     * type returned by the promotion.
     *
     * @template U
     *
     * @param T $subject
     * @param callable(T): Either<Error, U> $promote
     *
     * @return TaskEither<list<Error>, U>
     */
    public function validateInto(mixed $subject, callable $promote): TaskEither
    {
        return $this->chain($subject)
            ->flatMap(static fn (mixed $validated): TaskEither => TaskEither::fromEither($promote($validated)))
            ->mapLeft(self::asList(...));
    }

    /**
     * @param T $subject
     *
     * @return TaskEither<Error, T>
     */
    private function chain(mixed $subject): TaskEither
    {
        $chain = TaskEither::right($subject);

        foreach ($this->rules as $rule) {
            $chain = $chain->flatMap($rule->apply(...));
        }

        return $chain;
    }

    /**
     * @return list<Error>
     */
    private static function asList(Error $error): array
    {
        return [$error];
    }
}
