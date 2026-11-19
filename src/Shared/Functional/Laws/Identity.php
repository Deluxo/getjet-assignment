<?php

declare(strict_types=1);

namespace App\Shared\Functional\Laws;

/**
 * Wraps a value in a zero-argument thunk, for use where a callable is expected.
 */
final class Identity
{
    /**
     * @template T
     *
     * @param T $value
     *
     * @return \Closure(): T
     */
    public static function of(mixed $value): \Closure
    {
        return fn() => $value;
    }
}
