<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Persistence\Doctrine;

use App\Notification\Domain\Port\Out\NotificationStore;
use App\Notification\Domain\ShiftNotification;
use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Shared\Functional\TaskEither;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;

final readonly class DoctrineNotificationStore implements NotificationStore
{
    private const int MAX_ERROR_LENGTH = 500;

    private const string INSERT_SQL = <<<'SQL'
        INSERT INTO shift_notifications (id, shift_id, employee_id, date, start_time, end_time)
        VALUES (:id, :shift_id, :employee_id, :date, :start_time, :end_time)
        ON CONFLICT (id) DO NOTHING
        SQL;

    private const string MARK_SENT_SQL = <<<'SQL'
        UPDATE shift_notifications
        SET sent_at = CURRENT_TIMESTAMP
        WHERE id = :id
        SQL;

    private const string MARK_FAILED_SQL = <<<'SQL'
        UPDATE shift_notifications
        SET attempts = attempts + 1,
            last_attempt_at = CURRENT_TIMESTAMP,
            last_error = :error
        WHERE id = :id
        SQL;

    private const string PENDING_SQL = <<<'SQL'
        SELECT shift_id, employee_id, date, start_time, end_time
        FROM shift_notifications
        WHERE sent_at IS NULL
          AND attempts < :max_attempts
          AND created_at <= :created_before
        ORDER BY created_at, id
        LIMIT :limit
        SQL;

    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
    ) {
    }

    public function record(ShiftNotification $notification): TaskEither
    {
        return TaskEither::defer(function () use ($notification): Either {
            try {
                $affected = (int) $this->connection->executeStatement(self::INSERT_SQL, [
                    'id' => $notification->id(),
                    'shift_id' => $notification->shiftId,
                    'employee_id' => $notification->employeeId,
                    'date' => $notification->date->format('Y-m-d'),
                    'start_time' => $notification->startTime->format('H:i:s'),
                    'end_time' => $notification->endTime->format('H:i:s'),
                ]);

                return Either::Right($affected > 0);
            } catch (DbalException $exception) {
                $this->logger->error('Could not record a shift notification.', [
                    'notification_id' => $notification->id(),
                    'exception' => $exception,
                ]);

                return Either::Left(new Error('notification_store', 'The notification could not be recorded.'));
            }
        });
    }

    public function pending(\DateTimeImmutable $createdBefore, int $limit, int $maxAttempts): TaskEither
    {
        return TaskEither::defer(function () use ($createdBefore, $limit, $maxAttempts): Either {
            try {
                $rows = $this->connection->executeQuery(
                    self::PENDING_SQL,
                    [
                        'max_attempts' => $maxAttempts,
                        // SQLite's CURRENT_TIMESTAMP is UTC; compare in the same zone.
                        'created_before' => $createdBefore->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                        'limit' => $limit,
                    ],
                    [
                        'max_attempts' => ParameterType::INTEGER,
                        'created_before' => ParameterType::STRING,
                        'limit' => ParameterType::INTEGER,
                    ],
                )->fetchAllAssociative();

                return Either::Right(array_map(self::hydrate(...), $rows));
            } catch (DbalException|\UnexpectedValueException $exception) {
                $this->logger->error('Could not load pending shift notifications.', [
                    'exception' => $exception,
                ]);

                return Either::Left(new Error('notification_store', 'Pending notifications could not be loaded.'));
            }
        });
    }

    public function markSent(string $notificationId): TaskEither
    {
        return $this->update(self::MARK_SENT_SQL, ['id' => $notificationId], $notificationId);
    }

    public function markFailed(string $notificationId, Error $error): TaskEither
    {
        return $this->update(self::MARK_FAILED_SQL, [
            'id' => $notificationId,
            'error' => mb_substr($error->message(), 0, self::MAX_ERROR_LENGTH),
        ], $notificationId);
    }

    /**
     * @param array<string, string> $parameters
     *
     * @return TaskEither<Error, null>
     */
    private function update(string $sql, array $parameters, string $notificationId): TaskEither
    {
        return TaskEither::defer(function () use ($sql, $parameters, $notificationId): Either {
            try {
                $this->connection->executeStatement($sql, $parameters);

                return Either::Right(null);
            } catch (DbalException $exception) {
                $this->logger->error('Could not update a shift notification.', [
                    'notification_id' => $notificationId,
                    'exception' => $exception,
                ]);

                return Either::Left(new Error('notification_store', 'The notification state could not be updated.'));
            }
        });
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): ShiftNotification
    {
        return new ShiftNotification(
            self::string($row, 'shift_id'),
            self::string($row, 'employee_id'),
            self::parse(self::string($row, 'date'), 'Y-m-d'),
            self::parse(self::string($row, 'start_time'), 'H:i:s'),
            self::parse(self::string($row, 'end_time'), 'H:i:s'),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function string(array $row, string $column): string
    {
        $value = $row[$column] ?? null;

        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf('Expected a string in column "%s".', $column));
        }

        return $value;
    }

    private static function parse(string $value, string $format): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);

        if (false === $date) {
            throw new \UnexpectedValueException(sprintf('Cannot parse "%s" as "%s".', $value, $format));
        }

        return $date;
    }
}
