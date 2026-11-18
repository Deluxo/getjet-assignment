<?php

declare(strict_types=1);

namespace App\Shared\Functional\Laws;

interface Monad extends Applicative
{
    /**
     * @param callable $transform
     */
    public function flatMap(callable $transform): mixed;
}
