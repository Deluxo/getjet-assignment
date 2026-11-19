<?php

declare(strict_types=1);

namespace App\Shared\Functional\Laws;

/**
 * @extends Functor<T>
 *
 * @template T
 */
interface Applicative extends Functor
{
    /**
     * @template U
     *
     * @param U $value
     *
     * @return static
     */
    public static function of(mixed $value): self;

    /**
     * Applies a wrapped function to this wrapped value.
     *
     * @param Applicative<T> $wrappedFn
     *
     * @return mixed
     */
    public function ap(self $wrappedFn): mixed;
}
