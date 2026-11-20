<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Infrastructure\Persistence;

use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Shift\Domain\Port\In\CreateShiftRequest;
use App\Shift\Domain\Port\Out\Shift;
use App\Shift\Infrastructure\Persistence\Doctrine\DoctrineShiftRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class DoctrineShiftRepositoryTest extends TestCase
{
    public function testRetriesWhenTheDatabaseIsLocked(): void
    {
        $lock = self::lockWaitTimeout();
        $attempts = 0;

        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $params = [], array $types = []) use (&$attempts, $lock): int {
                ++$attempts;

                if ($attempts < 3) {
                    throw $lock;
                }

                return 1;
            },
        );

        $repository = new DoctrineShiftRepository($connection, $this->createStub(LoggerInterface::class));
        $shift = self::shift();

        self::assertSame($shift, self::right($repository->create($shift)->run()));
        self::assertSame(3, $attempts);
    }

    public function testReturnsAnOverlapErrorWhenTheInsertIsSkipped(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('executeStatement')->willReturn(0);

        $repository = new DoctrineShiftRepository($connection, $this->createStub(LoggerInterface::class));

        $result = $repository->create(self::shift())->run();

        self::assertEquals(
            Error::forField('startTime', 'overlaps another shift for this employee.'),
            self::left($result),
        );
    }

    public function testGivesUpWhenTheDatabaseStaysLocked(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::exactly(3))
            ->method('executeStatement')
            ->willThrowException(self::lockWaitTimeout());

        $repository = new DoctrineShiftRepository($connection, $this->createStub(LoggerInterface::class));

        $result = $repository->create(self::shift())->run();

        self::assertEquals(
            new Error('shift_repository', 'The shift could not be saved because the database is busy.'),
            self::left($result),
        );
    }

    private static function lockWaitTimeout(): LockWaitTimeoutException
    {
        $driverException = new class('database is locked') extends \RuntimeException implements DriverException {
            public function getSQLState(): ?string
            {
                return null;
            }
        };

        return new LockWaitTimeoutException($driverException, null);
    }

    private static function shift(): Shift
    {
        return Shift::create(
            'employee-1',
            self::parse('2026-10-06', 'Y-m-d'),
            self::parse('09:00', 'H:i'),
            self::parse('17:00', 'H:i'),
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
