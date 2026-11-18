<?php

declare(strict_types=1);

namespace App\Shared\Functional;

use App\Shared\Functional\EitherImpl\Left;
use App\Shared\Functional\EitherImpl\Right;
use App\Shared\Functional\Laws\Monad;

/**
 * A value that is either a success (Right) or a failure (Left).
 *
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
     * @template E
     *
     * @param E $error
     *
     * @return Left<E>
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
     * @param callable $onRight
     * @param callable $onLeft
     *
     * @return mixed
     */
    abstract public function fold(callable $onRight, callable $onLeft): mixed;

    /**
     * @param callable $transform
     *
     * @return static
     */
    public function mapError(callable $transform): self
    {
        return $this->fold(
            static fn (mixed $value): self => new Right($value),
            static fn (mixed $error): self => new Left($transform($error)),
        );
    }
}
