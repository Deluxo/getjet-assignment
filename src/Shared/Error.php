<?php

declare(strict_types=1);

namespace App\Shared;

final readonly class Error
{
    public function __construct(
        private string $code,
        private string $message,
    ) {
    }

    /**
     * Builds an error whose code is the offending field name.
     */
    public static function forField(string $field, string $detail): self
    {
        return new self($field, sprintf('Field "%s" %s', $field, $detail));
    }

    public function code(): string
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }
}
