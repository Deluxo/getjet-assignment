<?php

declare(strict_types=1);

namespace App\Shared\Functional\EitherImpl;

use App\Shared\Functional\Either;
use App\Shared\Functional\Laws\Applicative;

/**
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
     * @param callable $transform
     *
     * @return Right<mixed>
     */
    public function map(callable $transform): self
    {
        return new self($transform($this->value));
    }

    /**
     * @param callable $transform
     *
     * @return Either<mixed>
     */
    public function flatMap(callable $transform): Either
    {
        return $transform($this->value);
    }

    /**
     * @param Applicative $wrappedFn
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
     * @param callable $onRight
     * @param callable $onLeft
     *
     * @return mixed
     */
    public function fold(callable $onRight, callable $onLeft): mixed
    {
        return $onRight($this->value);
    }
}
