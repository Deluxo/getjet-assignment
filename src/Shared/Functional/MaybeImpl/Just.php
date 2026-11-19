<?php

declare(strict_types=1);

namespace App\Shared\Functional\MaybeImpl;

use App\Shared\Functional\Laws\Applicative;
use App\Shared\Functional\Maybe;

/**
 * @template T
 */
final readonly class Just extends Maybe
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

    public function isJust(): bool
    {
        return true;
    }

    /**
     * @template U
     *
     * @param callable(T):U $transform
     *
     * @return Just<U>
     */
    public function map(callable $transform): self
    {
        return new self($transform($this->value));
    }

    /**
     * @param callable(T): mixed $transform
     *
     * @return Maybe<mixed>
     */
    public function flatMap(callable $transform): Maybe
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
            return new Nothing();
        }

        return new Just(($wrappedFn->value())($this->value));
    }

    /**
     * @param callable $onNothing
     * @param callable $onJust
     *
     * @return mixed
     */
    public function fold(callable $onNothing, callable $onJust): mixed
    {
        return $onJust($this->value);
    }
}
