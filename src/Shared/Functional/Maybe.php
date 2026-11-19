<?php

declare(strict_types=1);

namespace App\Shared\Functional;

use App\Shared\Functional\Laws\Monad;
use App\Shared\Functional\MaybeImpl\Just;
use App\Shared\Functional\MaybeImpl\Nothing;

/**
 * A value that is either present (Just) or absent (Nothing).
 *
 * @implements Monad<T>
 *
 * @template T
 */
abstract readonly class Maybe implements Monad
{
    /**
     * @template U
     *
     * @param U $value
     *
     * @return Just<U>
     */
    public static function of(mixed $value): self
    {
        return new Just($value);
    }

    /**
     * @template U
     *
     * @param U $value
     *
     * @return Just<U>
     */
    public static function just(mixed $value): self
    {
        return new Just($value);
    }

    /**
     * @return Nothing<T>
     */
    public static function nothing(): self
    {
        return new Nothing();
    }

    /**
     * @param callable $onNothing
     * @param callable $onJust
     *
     * @return mixed
     */
    abstract public function fold(callable $onNothing, callable $onJust): mixed;

    /**
     * @param mixed $default
     *
     * @return mixed
     */
    public function getOrElse(mixed $default): mixed
    {
        return $this->fold(
            static fn(): mixed => $default,
            static fn(mixed $value): mixed => $value,
        );
    }

    abstract public function isJust(): bool;

    public function isNothing(): bool
    {
        return !$this->isJust();
    }

    public function nullish(mixed $value): self
    {
        return $value ? self::just($value) : self::nothing();
    }
}
