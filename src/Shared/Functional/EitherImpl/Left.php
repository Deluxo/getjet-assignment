<?php

declare(strict_types=1);

namespace App\Shared\Functional\EitherImpl;

use App\Shared\Functional\Either;
use App\Shared\Functional\Laws\Applicative;

/**
 * @template E
 */
final readonly class Left extends Either
{
    /**
     * @param E $error
     */
    public function __construct(
        private mixed $error,
    ) {
    }

    /**
     * @return E
     */
    public function value(): mixed
    {
        return $this->error;
    }

    public function isRight(): bool
    {
        return false;
    }

    /**
     * @param callable $transform
     *
     * @return Left<E>
     */
    public function map(callable $transform): self
    {
        return new self($this->error);
    }

    /**
     * @param callable $transform
     *
     * @return Left<E>
     */
    public function flatMap(callable $transform): Either
    {
        return new self($this->error);
    }

    /**
     * @param Applicative $wrappedFn
     *
     * @return Left<E>
     */
    public function ap(Applicative $wrappedFn): mixed
    {
        return new self($this->error);
    }

    /**
     * @param callable $onRight
     * @param callable $onLeft
     *
     * @return mixed
     */
    public function fold(callable $onRight, callable $onLeft): mixed
    {
        return $onLeft($this->error);
    }
}
