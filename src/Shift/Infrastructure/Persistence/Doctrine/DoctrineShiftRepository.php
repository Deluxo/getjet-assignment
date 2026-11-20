<?php

declare(strict_types=1);

namespace App\Shift\Infrastructure\Persistence\Doctrine;

use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Shared\Functional\TaskEither;
use App\Shift\Domain\Port\Out\Shift;
use App\Shift\Domain\Port\Out\ShiftRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Exception\RetryableException;
use Psr\Log\LoggerInterface;

final readonly class DoctrineShiftRepository implements ShiftRepository
{
    private const int MAX_ATTEMPTS = 3;

    private const int RETRY_DELAY_MICROSECONDS = 10_000;

    /**
     * SQLite has no exclusion constraints, so the interval check and the insert
     * are a single statement. SQLite serializes writers; a concurrent attempt
     * gets "database is locked" and is retried, after which it sees the
     * committed row and inserts nothing.
     */
    private const string INSERT_SQL = <<<'SQL'
        INSERT INTO shifts (id, employee_id, date, start_time, end_time, country_code)
        SELECT :id, :employee_id, :date, :start_time, :end_time, :country_code
        WHERE NOT EXISTS (
            SELECT 1
            FROM shifts
            WHERE employee_id = :overlap_employee_id
              AND date = :overlap_date
              AND start_time < :overlap_end_time
              AND end_time > :overlap_start_time
        )
        SQL;

    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return TaskEither<Error, Shift>
     */
    public function create(Shift $shift): TaskEither
    {
        return TaskEither::defer(function () use ($shift): Either {
            for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
                try {
                    $affected = (int) $this->connection->executeStatement(self::INSERT_SQL, self::parameters($shift));

                    if (0 === $affected) {
                        return Either::Left(Error::forField('startTime', 'overlaps another shift for this employee.'));
                    }

                    return Either::Right($shift);
                } catch (RetryableException $exception) {
                    $this->logger->warning('Retrying a shift insert after a database lock.', [
                        'attempt' => $attempt,
                        'exception' => $exception,
                    ]);

                    if (self::MAX_ATTEMPTS === $attempt) {
                        return Either::Left(new Error('shift_repository', 'The shift could not be saved because the database is busy.'));
                    }

                    usleep(self::RETRY_DELAY_MICROSECONDS * $attempt);
                } catch (DbalException $exception) {
                    $this->logger->error('Could not save a shift.', ['exception' => $exception]);

                    return Either::Left(new Error('shift_repository', '.'));
                }
            }

            return Either::Left(new Error('shift_repository', 'The shift could not be saved.'));
        });
    }

    /**
     * @return array<string, string>
     */
    private static function parameters(Shift $shift): array
    {
        return [
            'id' => $shift->id,
            'employee_id' => $shift->employeeId,
            'date' => $shift->date->format('Y-m-d'),
            'start_time' => $shift->startTime->format('H:i:s'),
            'end_time' => $shift->endTime->format('H:i:s'),
            'country_code' => $shift->countryCode,
            'overlap_employee_id' => $shift->employeeId,
            'overlap_date' => $shift->date->format('Y-m-d'),
            'overlap_start_time' => $shift->startTime->format('H:i:s'),
            'overlap_end_time' => $shift->endTime->format('H:i:s'),
        ];
    }
}
