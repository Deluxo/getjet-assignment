<?php

declare(strict_types=1);

namespace App\Notification\Domain;

use App\Shared\Infrastructure\Api\Json\ToJson;
use App\Shift\Domain\Port\Out\Shift;

/**
 * The facts a notification is about. Its identity is derived from those facts,
 * so the same persisted shift can only ever produce one notification.
 */
final readonly class ShiftNotification implements ToJson
{
    private const string KEY_VERSION = 'shift-notification:v1';

    public function __construct(
        public string $shiftId,
        public string $employeeId,
        public \DateTimeImmutable $date,
        public \DateTimeImmutable $startTime,
        public \DateTimeImmutable $endTime,
    ) {
    }

    public static function forShift(Shift $shift): self
    {
        return new self(
            $shift->id,
            $shift->employeeId,
            $shift->date,
            $shift->startTime,
            $shift->endTime,
        );
    }

    public function id(): string
    {
        return hash('sha256', implode('|', [
            self::KEY_VERSION,
            $this->shiftId,
            $this->employeeId,
            $this->date->format('Y-m-d'),
            $this->startTime->format('H:i'),
            $this->endTime->format('H:i'),
        ]));
    }

    /**
     * @return array<string, string>
     */
    public function toJson(): array
    {
        return [
            'id' => $this->id(),
            'shift_id' => $this->shiftId,
            'employee_id' => $this->employeeId,
            'date' => $this->date->format('Y-m-d'),
            'start_time' => $this->startTime->format('H:i'),
            'end_time' => $this->endTime->format('H:i'),
        ];
    }
}
