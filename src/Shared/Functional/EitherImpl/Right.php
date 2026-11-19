<?php

declare(strict_types=1);

namespace App\Shared\Functional\EitherImpl;

use App\Shared\Functional\Either;
use App\Shared\Functional\Laws\Applicative;

/**
 * @extends Either<never, T>
 *
 * @template T
 */
final readonly class Right extends Either
{
    /**
     * @param T $value
     */
    public function __construct(
        private mixed $value,
    ) {
    }

    /**
     * @return T
     */
    public function value(): mixed
    {
        return $this->value;
    }

    public function isRight(): bool
    {
        return true;
    }

    /**
     * @template U
     *
     * @param callable(T):U $transform
     *
     * @return Right<U>
     */
    public function map(callable $transform): self
    {
        return new self($transform($this->value));
    }

    /**
     * @param callable(T): mixed $transform
     *
     * @return Either<mixed, mixed>
     */
    public function flatMap(callable $transform): Either
    {
        return $transform($this->value);
    }

    /**
     * @param Applicative<mixed> $wrappedFn
     *
     * @return mixed
     */
    public function ap(Applicative $wrappedFn): mixed
    {
        if (!$wrappedFn instanceof self) {
            return new Left($wrappedFn);
        }

        return new Right(($wrappedFn->value())($this->value));
    }

    /**
     * @template U
     *
     * @param callable(never):U $onLeft
     * @param callable(T):U $onRight
     *
     * @return U
     */
    public function fold(callable $onLeft, callable $onRight): mixed
    {
        return $onRight($this->value);
    }
}
