<?php

declare(strict_types=1);

namespace App\Shared\Functional\Laws;

/**
 * @extends Applicative<T>
 *
 * @template T
 */
interface Monad extends Applicative
{
    /**
     * @param callable(T): mixed $transform
     *
     * @return mixed
     */
    public function flatMap(callable $transform): mixed;
}
