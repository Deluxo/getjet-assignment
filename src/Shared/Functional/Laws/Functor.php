<?php

declare(strict_types=1);

namespace App\Shared\Functional\Laws;

/**
 * @template T
 */
interface Functor
{
    /**
     * @template U
     *
     * @param callable(T):U $transform
     *
     * @return mixed
     */
    public function map(callable $transform): mixed;
}
