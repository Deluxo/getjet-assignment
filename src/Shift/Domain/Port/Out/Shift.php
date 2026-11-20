<?php

declare(strict_types=1);

namespace App\Shift\Domain\Port\Out;

use App\Shared\Infrastructure\Api\Json\ToJson;
use Symfony\Component\Uid\Uuid;

final readonly class Shift implements ToJson
{
    public function __construct(
        public string $id,
        public string $employeeId,
        public \DateTimeImmutable $date,
        public \DateTimeImmutable $startTime,
        public \DateTimeImmutable $endTime,
        public string $countryCode,
    ) {}

    public static function create(
        string $employeeId,
        \DateTimeImmutable $date,
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $endTime,
        string $countryCode,
    ): self {
        return new self(
            Uuid::v7()->toRfc4122(),
            $employeeId,
            $date,
            $startTime,
            $endTime,
            strtoupper($countryCode),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toJson(): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employeeId,
            'date' => $this->date->format('Y-m-d'),
            'start_time' => $this->startTime->format('H:i'),
            'end_time' => $this->endTime->format('H:i'),
            'country_code' => $this->countryCode,
        ];
    }
}
