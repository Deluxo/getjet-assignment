<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notification\Infrastructure\Persistence;

use App\Notification\Domain\Port\Out\NotificationStore;
use App\Notification\Domain\ShiftNotification;
use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Shift\Domain\Port\In\CreateShiftRequest;
use App\Shift\Domain\Port\Out\Shift;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineNotificationStoreTest extends KernelTestCase
{
    private NotificationStore $store;

    protected function setUp(): void
    {
        self::bootKernel();

        $store = self::getContainer()->get(NotificationStore::class);

        if (!$store instanceof NotificationStore) {
            throw new \LogicException('Expected the notification store service.');
        }

        $this->store = $store;
        self::connection()->executeStatement('DELETE FROM shift_notifications');
    }

    public function testRecordsTheNotification(): void
    {
        $notification = self::notification();

        self::assertTrue(self::right($this->store->record($notification)->run()));

        $row = self::connection()->fetchAssociative(
            'SELECT * FROM shift_notifications WHERE id = ?',
            [$notification->id()],
        );

        self::assertIsArray($row);
        self::assertSame('shift-1', $row['shift_id']);
        self::assertSame('employee-1', $row['employee_id']);
        self::assertSame('2026-10-06', $row['date']);
        self::assertSame('09:00:00', $row['start_time']);
        self::assertSame('17:00:00', $row['end_time']);
        self::assertNull($row['sent_at']);
        self::assertSame(0, $row['attempts']);
        self::assertNotNull($row['created_at']);
    }

    public function testRefusesToRecordTheSameNotificationTwice(): void
    {
        $notification = self::notification();

        self::assertTrue(self::right($this->store->record($notification)->run()));
        self::assertFalse(self::right($this->store->record($notification)->run()));

        self::assertSame(1, self::rowCount());
    }

    public function testPendingReturnsUnsentNotificationsOldestFirst(): void
    {
        $first = self::notification(employeeId: 'employee-1');
        $second = self::notification(employeeId: 'employee-2');
        $third = self::notification(employeeId: 'employee-3');

        foreach ([$first, $second, $third] as $notification) {
            self::right($this->store->record($notification)->run());
        }

        self::connection()->executeStatement(
            'UPDATE shift_notifications SET created_at = ? WHERE id = ?',
            ['2026-10-01 08:00:00', $third->id()],
        );
        self::connection()->executeStatement(
            'UPDATE shift_notifications SET created_at = ? WHERE id = ?',
            ['2026-10-03 08:00:00', $first->id()],
        );
        self::connection()->executeStatement(
            'UPDATE shift_notifications SET created_at = ? WHERE id = ?',
            ['2026-10-02 08:00:00', $second->id()],
        );
        self::right($this->store->markSent($second->id())->run());

        $pending = self::right($this->store->pending(new \DateTimeImmutable('+1 minute'), 10, 5)->run());

        self::assertSame([$third->id(), $first->id()], array_map(
            static fn (ShiftNotification $notification): string => $notification->id(),
            $pending,
        ));
    }

    public function testPendingRespectsTheLimit(): void
    {
        foreach (['employee-1', 'employee-2', 'employee-3'] as $employeeId) {
            self::right($this->store->record(self::notification(employeeId: $employeeId))->run());
        }

        $pending = self::right($this->store->pending(new \DateTimeImmutable('+1 minute'), 2, 5)->run());

        self::assertCount(2, $pending);
    }

    public function testPendingSkipsNotificationsNewerThanTheCutoff(): void
    {
        self::right($this->store->record(self::notification())->run());

        $pending = self::right($this->store->pending(new \DateTimeImmutable('-1 minute'), 10, 5)->run());

        self::assertSame([], $pending);
    }

    public function testPendingSkipsNotificationsThatExhaustedTheirAttempts(): void
    {
        $notification = self::notification();
        self::right($this->store->record($notification)->run());

        foreach (range(1, 5) as $attempt) {
            self::right($this->store->markFailed($notification->id(), new Error('notification_sender', 'boom'))->run());
        }

        self::assertSame([], self::right($this->store->pending(new \DateTimeImmutable('+1 minute'), 10, 5)->run()));
        self::assertCount(1, self::right($this->store->pending(new \DateTimeImmutable('+1 minute'), 10, 6)->run()));
    }

    public function testHydratesTheStoredNotification(): void
    {
        $notification = self::notification();
        self::right($this->store->record($notification)->run());

        $pending = self::right($this->store->pending(new \DateTimeImmutable('+1 minute'), 10, 5)->run());

        self::assertCount(1, $pending);
        self::assertSame($notification->shiftId, $pending[0]->shiftId);
        self::assertSame($notification->employeeId, $pending[0]->employeeId);
        self::assertSame($notification->date->format('Y-m-d'), $pending[0]->date->format('Y-m-d'));
        self::assertSame($notification->startTime->format('H:i'), $pending[0]->startTime->format('H:i'));
        self::assertSame($notification->endTime->format('H:i'), $pending[0]->endTime->format('H:i'));
        self::assertSame($notification->id(), $pending[0]->id());
    }

    public function testMarksTheNotificationAsSent(): void
    {
        $notification = self::notification();
        self::right($this->store->record($notification)->run());

        self::right($this->store->markSent($notification->id())->run());

        $row = self::connection()->fetchAssociative(
            'SELECT sent_at FROM shift_notifications WHERE id = ?',
            [$notification->id()],
        );

        self::assertIsArray($row);
        self::assertNotNull($row['sent_at']);
    }

    public function testMarksTheNotificationAsFailed(): void
    {
        $notification = self::notification();
        self::right($this->store->record($notification)->run());

        self::right($this->store->markFailed($notification->id(), new Error('notification_sender', 'disk full'))->run());

        $row = self::connection()->fetchAssociative(
            'SELECT attempts, last_attempt_at, last_error FROM shift_notifications WHERE id = ?',
            [$notification->id()],
        );

        self::assertIsArray($row);
        self::assertSame(1, $row['attempts']);
        self::assertNotNull($row['last_attempt_at']);
        self::assertSame('disk full', $row['last_error']);
    }

    private static function rowCount(): int
    {
        $count = self::connection()->fetchOne('SELECT COUNT(*) FROM shift_notifications');

        if (!is_numeric($count)) {
            throw new \LogicException('Expected a numeric row count.');
        }

        return (int) $count;
    }

    private static function notification(
        string $shiftId = 'shift-1',
        string $employeeId = 'employee-1',
        string $date = '2026-10-06',
        string $startTime = '09:00',
        string $endTime = '17:00',
    ): ShiftNotification {
        return new ShiftNotification(
            $shiftId,
            $employeeId,
            self::date($date),
            self::time($startTime),
            self::time($endTime),
        );
    }

    private static function date(string $value): \DateTimeImmutable
    {
        $parsed = CreateShiftRequest::parseDate($value);

        if (null === $parsed) {
            throw new \LogicException(sprintf('Cannot parse "%s".', $value));
        }

        return $parsed;
    }

    private static function time(string $value): \DateTimeImmutable
    {
        $parsed = CreateShiftRequest::parseTime($value);

        if (null === $parsed) {
            throw new \LogicException(sprintf('Cannot parse "%s".', $value));
        }

        return $parsed;
    }

    private static function connection(): Connection
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \LogicException('Expected the entity manager service.');
        }

        return $entityManager->getConnection();
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
            static fn (Error $error): never => throw new \LogicException($error->message()),
            static fn (mixed $value): mixed => $value,
        );
    }
}
