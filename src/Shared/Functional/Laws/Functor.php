<?php

declare(strict_types=1);

namespace App\Shared\Functional\Laws;

interface Functor
{
    /**
     * @param callable $transform
     */
    public function map(callable $transform): mixed;
}
