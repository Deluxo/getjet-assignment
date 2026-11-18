<?php

declare(strict_types=1);

namespace App\Shared\Functional\Laws;

interface Applicative extends Functor
{
    public static function of(mixed $value): self;

    /**
     * Applies a wrapped function to this wrapped value.
     */
    public function ap(self $wrappedFn): mixed;
}
