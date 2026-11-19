<?php

declare(strict_types=1);

namespace App\Shift\Application;

use App\Shared\Error;
use App\Shared\Functional\TaskEither;

final class ShiftBuilder
{
    public function __construct(
        public readonly ?string $employeeId = null,
        public readonly ?string $date = null,
        public readonly ?string $startTime = null,
        public readonly ?string $endTime = null,
        public readonly ?string $countryCode = null,
    ) {
    }

    public function withEmployeeId(string $employeeId): self
    {
        return new self($employeeId, $this->date, $this->startTime, $this->endTime, $this->countryCode);
    }

    public function withDate(string $date): self
    {
        return new self($this->employeeId, $date, $this->startTime, $this->endTime, $this->countryCode);
    }

    public function withStartTime(string $startTime): self
    {
        return new self($this->employeeId, $this->date, $startTime, $this->endTime, $this->countryCode);
    }

    public function withEndTime(string $endTime): self
    {
        return new self($this->employeeId, $this->date, $this->startTime, $endTime, $this->countryCode);
    }

    public function withCountryCode(string $countryCode): self
    {
        return new self($this->employeeId, $this->date, $this->startTime, $this->endTime, $countryCode);
    }

    /**
     * @return TaskEither<Error[], Shift>
     */
    public function build(): TaskEither
    {
        return TaskEither::defer(static function (): never {
            throw new \LogicException('ShiftBuilder::build() is not implemented yet.');
        });
    }
}
