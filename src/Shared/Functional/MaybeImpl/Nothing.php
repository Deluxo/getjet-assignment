<?php

declare(strict_types=1);

namespace App\Shared\Functional\MaybeImpl;

use App\Shared\Functional\Laws\Applicative;
use App\Shared\Functional\Maybe;

/**
 * @template T
 */
final readonly class Nothing extends Maybe
{
    /**
     * @return never
     */
    public function value(): mixed
    {
        throw new \LogicException('Nothing has no value.');
    }

    public function isJust(): bool
    {
        return false;
    }

    /**
     * @param callable $transform
     *
     * @return Nothing<never>
     */
    public function map(callable $transform): self
    {
        return new self();
    }

    /**
     * @param callable $transform
     *
     * @return Nothing<never>
     */
    public function flatMap(callable $transform): Maybe
    {
        return new self();
    }

    /**
     * @param Applicative<mixed> $wrappedFn
     *
     * @return Nothing<never>
     */
    public function ap(Applicative $wrappedFn): mixed
    {
        return new self();
    }

    /**
     * @param callable $onJust
     * @param callable $onNothing
     *
     * @return mixed
     */
    public function fold(callable $onJust, callable $onNothing): mixed
    {
        return $onNothing();
    }
}
