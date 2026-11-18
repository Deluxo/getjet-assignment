<?php

declare(strict_types=1);

namespace App\Shared\Functional;

/**
 * A lazy computation. Nothing inside the thunk runs until {@see self::run()} is called,
 * and a task (or any task derived from it) can only be executed once.
 *
 * @template T
 */
final class Task
{
    /**
     * @var \Closure(): T
     */
    private readonly \Closure $thunk;

    private bool $started = false;

    /**
     * @param \Closure(): T $thunk
     */
    private function __construct(\Closure $thunk)
    {
        $this->thunk = $thunk;
    }

    /**
     * @template U
     *
     * @param \Closure(): U $thunk
     *
     * @return self<U>
     */
    public static function of(\Closure $thunk): self
    {
        return new self($thunk);
    }

    /**
     * @template U
     *
     * @param callable(T): U $transform
     *
     * @return self<U>
     */
    public function map(callable $transform): self
    {
        return new self(function () use ($transform): mixed {
            return $transform($this->run());
        });
    }

    /**
     * @template U
     *
     * @param callable(T): self<U> $transform
     *
     * @return self<U>
     */
    public function flatMap(callable $transform): self
    {
        return new self(function () use ($transform): mixed {
            $next = $transform($this->run());

            return $next->run();
        });
    }

    /**
     * @return T
     */
    public function run(): mixed
    {
        if ($this->started) {
            throw new \LogicException('This task has already been run.');
        }

        $this->started = true;

        return ($this->thunk)();
    }
}
