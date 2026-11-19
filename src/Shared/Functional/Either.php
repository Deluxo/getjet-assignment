<?php

declare(strict_types=1);

namespace App\Shared\Functional;

use App\Shared\Functional\EitherImpl\Left;
use App\Shared\Functional\EitherImpl\Right;
use App\Shared\Functional\Laws\Monad;

/**
 * A value that is either a success (Right) or a failure (Left).
 *
 * @implements Monad<T>
 *
 * @template E
 * @template T
 */
abstract readonly class Either implements Monad
{
    /**
     * @template U
     *
     * @param U $value
     *
     * @return Right<U>
     */
    public static function of(mixed $value): self
    {
        return new Right($value);
    }

    /**
     * @template U
     *
     * @param U $value
     *
     * @return Right<U>
     */
    public static function Right(mixed $value): self
    {
        return new Right($value);
    }

    /**
     * @template EL
     *
     * @param EL $error
     *
     * @return Left<EL>
     */
    public static function Left(mixed $error): self
    {
        return new Left($error);
    }

    abstract public function isRight(): bool;

    public function isLeft(): bool
    {
        return !$this->isRight();
    }

    /**
     * @template U
     *
     * @param callable(E):U $onLeft
     * @param callable(T):U $onRight
     *
     * @return U
     */
    abstract public function fold(callable $onLeft, callable $onRight): mixed;
}
