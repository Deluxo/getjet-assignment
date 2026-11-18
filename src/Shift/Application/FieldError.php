<?php

declare(strict_types=1);

namespace App\Shift\Application;

use App\Shared\Error;

final readonly class FieldError implements Error
{
    public function __construct(
        private string $field,
        private string $message,
    ) {
    }

    public function code(): string
    {
        return $this->field;
    }

    public function message(): string
    {
        return sprintf('Field "%s" %s', $this->field, $this->message);
    }
}
