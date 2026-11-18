<?php

declare(strict_types=1);

namespace App\Shift\Domain\Port\Out;

use App\Shared\Error;
use App\Shared\Infrastructure\Api\Json\ToJson;

final readonly class ShiftErrors implements ToJson
{
    /**
     * @param list<Error> $errors
     */
    public function __construct(private array $errors)
    {
    }

    /**
     * @return list<Error>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        return [
            'errors' => array_map(
                static fn (Error $error): string => $error->message(),
                $this->errors,
            ),
        ];
    }
}
