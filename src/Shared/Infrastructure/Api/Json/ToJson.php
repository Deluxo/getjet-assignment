<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Api\Json;

interface ToJson
{
    /**
     * @return array<string, mixed>
     */
    public function toJson(): array;
}
