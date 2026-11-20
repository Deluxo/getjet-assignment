<?php

declare(strict_types=1);

namespace App\Shared\Functional;

use App\Shared\Functional\Laws\Applicative;
use App\Shared\Functional\Laws\Monad;

/**
 * A lazy computation that either fails (Left) or succeeds (Right).
 *
 * Nothing inside the thunk runs until {@see self::run()} is called, and a
 * task either (or any task either derived from it) can only be executed once.
 *
 * @implements Monad<T>
 *
 * @template E
 * @template T
 */
final class TaskEither implements Monad
{
    /**
     * @var \Closure(): Either<E, T>
     */
    private readonly \Closure $thunk;

    private bool $started = false;

    /**
     * @param \Closure(): Either<E, T> $thunk
     */
    private function __construct(\Closure $thunk)
    {
        $this->thunk = $thunk;
    }

    /**
     * @template U
     *
     * @param U $value
     *
     * @return self<never, U>
     */
    public static function of(mixed $value): self
    {
        return self::right($value);
    }

    /**
     * @template EL
     * @template U
     *
     * @param Either<EL, U> $either
     *
     * @return self<EL, U>
     */
    public static function fromEither(Either $either): self
    {
        return new self(static fn (): Either => $either);
    }

    /**
     * @template U
     *
     * @param U $value
     *
     * @return self<never, U>
     */
    public static function right(mixed $value): self
    {
        return new self(static fn (): Either => Either::Right($value));
    }

    /**
     * @template EL
     *
     * @param EL $error
     *
     * @return self<EL, never>
     */
    public static function left(mixed $error): self
    {
        return new self(static fn (): Either => Either::Left($error));
    }

    /**
     * @template EL
     * @template U
     *
     * @param \Closure(): Either<EL, U> $thunk
     *
     * @return self<EL, U>
     */
    public static function defer(\Closure $thunk): self
    {
        return new self($thunk);
    }

    /**
     * @template U
     *
     * @param callable(T): U $transform
     *
     * @return self<E, U>
     */
    public function map(callable $transform): self
    {
        return new self(function () use ($transform): Either {
            return $this->run()->map($transform);
        });
    }

    /**
     * Runs a side effect on the right value and passes it through unchanged.
     *
     * @param callable(T): void $effect
     *
     * @return self<E, T>
     */
    public function tap(callable $effect): self
    {
        return $this->map(static function (mixed $value) use ($effect): mixed {
            $effect($value);

            return $value;
        });
    }

    /**
     * @template EF
     *
     * @param callable(E): EF $transform
     *
     * @return self<EF, T>
     */
    public function mapLeft(callable $transform): self
    {
        return new self(function () use ($transform): Either {
            return $this->run()->fold(
                static fn (mixed $error): Either => Either::Left($transform($error)),
                static fn (mixed $value): Either => Either::Right($value),
            );
        });
    }

    /**
     * @template EF
     * @template U
     *
     * @param callable(T): self<EF, U> $transform
     *
     * @return self<E|EF, U>
     */
    public function flatMap(callable $transform): self
    {
        return new self(function () use ($transform): Either {
            return $this->run()->fold(
                static fn (mixed $error): Either => Either::Left($error),
                static fn (mixed $value): Either => $transform($value)->run(),
            );
        });
    }

    /**
     * @param Applicative<mixed> $wrappedFn
     *
     * @return self<E, mixed>
     */
    public function ap(Applicative $wrappedFn): self
    {
        return new self(function () use ($wrappedFn): Either {
            return $this->run()->fold(
                static fn (mixed $error): Either => Either::Left($error),
                static fn (mixed $value): Either => $wrappedFn instanceof self
                    ? $wrappedFn->map(static fn (callable $fn): mixed => $fn($value))->run()
                    : Either::Left($wrappedFn),
            );
        });
    }

    /**
     * @template U
     *
     * @param callable(E): U $onLeft
     * @param callable(T): U $onRight
     *
     * @return Task<U>
     */
    public function fold(callable $onLeft, callable $onRight): Task
    {
        return Task::of(fn (): mixed => $this->run()->fold($onLeft, $onRight));
    }

    /**
     * @return Either<E, T>
     */
    public function run(): Either
    {
        if ($this->started) {
            throw new \LogicException('This task either has already been run.');
        }

        $this->started = true;

        return ($this->thunk)();
    }
}
