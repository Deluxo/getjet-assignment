<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shift\Infrastructure\Persistence;

use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Shift\Domain\Port\In\CreateShiftRequest;
use App\Shift\Domain\Port\Out\Shift;
use App\Shift\Domain\Port\Out\ShiftRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineShiftRepositoryTest extends KernelTestCase
{
    private ShiftRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $repository = self::getContainer()->get(ShiftRepository::class);

        if (!$repository instanceof ShiftRepository) {
            throw new \LogicException('Expected the shift repository service.');
        }

        $this->repository = $repository;
        self::connection()->executeStatement('DELETE FROM shifts');
    }

    public function testCreatesTheShift(): void
    {
        $shift = self::shift();

        $created = self::right($this->repository->create($shift)->run());

        self::assertSame($shift, $created);

        $row = self::connection()->fetchAssociative('SELECT * FROM shifts WHERE id = ?', [$shift->id]);

        self::assertIsArray($row);
        self::assertSame('employee-1', $row['employee_id']);
        self::assertSame('2026-10-06', $row['date']);
        self::assertSame('09:00:00', $row['start_time']);
        self::assertSame('17:00:00', $row['end_time']);
        self::assertSame('LT', $row['country_code']);
    }

    public function testRejectsAnOverlappingShift(): void
    {
        self::store(self::shift('employee-1', '2026-10-06', '09:00', '17:00'));

        $result = $this->repository
            ->create(self::shift('employee-1', '2026-10-06', '10:00', '18:00'))
            ->run();

        self::assertEquals(
            Error::forField('startTime', 'overlaps another shift for this employee.'),
            self::left($result),
        );
        self::assertSame(1, self::rowCount());
    }

    public function testRejectsAnExactDuplicateInterval(): void
    {
        self::store(self::shift('employee-1', '2026-10-06', '09:00', '17:00'));

        $result = $this->repository
            ->create(self::shift('employee-1', '2026-10-06', '09:00', '17:00'))
            ->run();

        self::assertEquals(
            Error::forField('startTime', 'overlaps another shift for this employee.'),
            self::left($result),
        );
        self::assertSame(1, self::rowCount());
    }

    public function testAllowsAShiftThatStartsWhenAnotherEnds(): void
    {
        self::store(self::shift('employee-1', '2026-10-06', '09:00', '17:00'));
        self::store(self::shift('employee-1', '2026-10-06', '17:00', '18:00'));
        self::store(self::shift('employee-1', '2026-10-06', '08:00', '09:00'));

        self::assertSame(3, self::rowCount());
    }

    public function testIgnoresShiftsOfOtherEmployeesOnTheSameDate(): void
    {
        self::store(self::shift('employee-1', '2026-10-06', '09:00', '17:00'));
        self::store(self::shift('employee-2', '2026-10-06', '09:00', '17:00'));

        self::assertSame(2, self::rowCount());
    }

    public function testIgnoresShiftsOnOtherDates(): void
    {
        self::store(self::shift('employee-1', '2026-10-06', '09:00', '17:00'));
        self::store(self::shift('employee-1', '2026-10-07', '09:00', '17:00'));

        self::assertSame(2, self::rowCount());
    }

    private function store(Shift $shift): void
    {
        self::right($this->repository->create($shift)->run());
    }

    private static function rowCount(): int
    {
        $count = self::connection()->fetchOne('SELECT COUNT(*) FROM shifts');

        if (!is_numeric($count)) {
            throw new \LogicException('Expected a numeric row count.');
        }

        return (int) $count;
    }

    private static function shift(
        string $employeeId = 'employee-1',
        string $date = '2026-10-06',
        string $startTime = '09:00',
        string $endTime = '17:00',
    ): Shift {
        return Shift::create(
            $employeeId,
            self::parse($date, 'Y-m-d'),
            self::parse($startTime, 'H:i'),
            self::parse($endTime, 'H:i'),
            'LT',
        );
    }

    private static function parse(string $value, string $format): \DateTimeImmutable
    {
        $parsed = 'Y-m-d' === $format
            ? CreateShiftRequest::parseDate($value)
            : CreateShiftRequest::parseTime($value);

        if (null === $parsed) {
            throw new \LogicException(sprintf('Cannot parse "%s".', $value));
        }

        return $parsed;
    }

    private static function connection(): Connection
    {
        return self::entityManager()->getConnection();
    }

    private static function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \LogicException('Expected the entity manager service.');
        }

        return $entityManager;
    }

    /**
     * @template T
     *
     * @param Either<Error, T> $either
     *
     * @return Error
     */
    private static function left(Either $either): mixed
    {
        return $either->fold(
            static fn(Error $error): mixed => $error,
            static fn(): never => throw new \LogicException('Expected a left value.'),
        );
    }

    /**
     * @template T
     *
     * @param Either<Error, T> $either
     *
     * @return T
     */
    private static function right(Either $either): mixed
    {
        return $either->fold(
            static fn(Error $error): never => throw new \LogicException($error->message()),
            static fn(mixed $value): mixed => $value,
        );
    }
}
