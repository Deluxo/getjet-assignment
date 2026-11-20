<?php

declare(strict_types=1);

namespace App\Shift\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shifts')]
#[ORM\Index(name: 'IDX_SHIFTS_EMPLOYEE_DATE', columns: ['employee_id', 'date'])]
final class ShiftEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        public string $id,
        #[ORM\Column(type: Types::STRING, length: 255)]
        public string $employeeId,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        public \DateTimeImmutable $date,
        #[ORM\Column(type: Types::TIME_IMMUTABLE)]
        public \DateTimeImmutable $startTime,
        #[ORM\Column(type: Types::TIME_IMMUTABLE)]
        public \DateTimeImmutable $endTime,
        #[ORM\Column(type: Types::STRING, length: 2)]
        public string $countryCode,
    ) {}
}
