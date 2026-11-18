<?php

declare(strict_types=1);

namespace App\Shared\Functional;

/**
 * A value that is either a success ({@see self::ok()}) or a failure ({@see self::fail()}).
 *
 * @template E
 * @template T
 */
final readonly class Either
{
    /**
     * @param T $value
     */
    private function __construct(
        private bool $ok,
        private mixed $value,
    ) {
    }

    /**
     * @template U
     *
     * @param U $value
     *
     * @return self<never, U>
     */
    public static function ok(mixed $value): self
    {
        return new self(true, $value);
    }

    /**
     * @template F
     *
     * @param F $error
     *
     * @return self<F, never>
     */
    public static function fail(mixed $error): self
    {
        return new self(false, $error);
    }

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function isFail(): bool
    {
        return !$this->ok;
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
        if (!$this->ok) {
            return self::fail($this->value);
        }

        return self::ok($transform($this->value));
    }

    /**
     * @template F
     *
     * @param callable(E): F $transform
     *
     * @return self<F, T>
     */
    public function mapError(callable $transform): self
    {
        if ($this->ok) {
            return self::ok($this->value);
        }

        return self::fail($transform($this->value));
    }

    /**
     * @template U
     *
     * @param callable(T): self<E, U> $transform
     *
     * @return self<E, U>
     */
    public function flatMap(callable $transform): self
    {
        if (!$this->ok) {
            return self::fail($this->value);
        }

        return $transform($this->value);
    }

    /**
     * @template U
     *
     * @param callable(T): U $onOk
     * @param callable(E): U $onFail
     *
     * @return U
     */
    public function fold(callable $onOk, callable $onFail): mixed
    {
        return $this->ok ? $onOk($this->value) : $onFail($this->value);
    }
}
