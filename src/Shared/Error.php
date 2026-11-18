<?php

declare(strict_types=1);

namespace App\Shared;

interface Error
{
    public function code(): string;

    public function message(): string;
}
