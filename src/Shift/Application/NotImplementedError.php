<?php

declare(strict_types=1);

namespace App\Shift\Application;

use App\Shared\Error;

final readonly class NotImplementedError implements Error
{
    public function code(): string
    {
        return 'not_implemented';
    }

    public function message(): string
    {
        return 'Not implemented.';
    }
}
