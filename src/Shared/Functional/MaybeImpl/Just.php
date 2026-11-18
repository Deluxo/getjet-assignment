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
     * @param callable $transform
     *
     * @return Just<mixed>
     */
    public function map(callable $transform): self
    {
        return new self($transform($this->value));
    }

    /**
     * @param callable $transform
     *
     * @return Maybe<mixed>
     */
    public function flatMap(callable $transform): Maybe
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
            return new Nothing();
        }

        return new Just(($wrappedFn->value())($this->value));
    }

    /**
     * @param callable $onJust
     * @param callable $onNothing
     *
     * @return mixed
     */
    public function fold(callable $onJust, callable $onNothing): mixed
    {
        return $onJust($this->value);
    }
}
