<?php

declare(strict_types=1);

namespace App\Shift\Domain\Port\Out;

use App\Shared\Infrastructure\Api\Json\ToJson;

final readonly class CreatedShift implements ToJson
{
    public function __construct(private string $id)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        return ['id' => $this->id];
    }
}
