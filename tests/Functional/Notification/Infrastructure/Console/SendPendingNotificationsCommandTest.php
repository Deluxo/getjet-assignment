<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notification\Infrastructure\Console;

use App\Notification\Domain\Port\Out\NotificationStore;
use App\Notification\Domain\ShiftNotification;
use App\Shift\Domain\Port\In\CreateShiftRequest;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SendPendingNotificationsCommandTest extends KernelTestCase
{
    private NotificationStore $store;

    private string $directory;

    private string $file;

    protected function setUp(): void
    {
        self::bootKernel();

        $store = self::getContainer()->get(NotificationStore::class);
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        if (!$store instanceof NotificationStore || !is_string($projectDir)) {
            throw new \LogicException('Expected the notification store and the project directory.');
        }

        $this->store = $store;
        $this->directory = $projectDir.'/var/notifications';
        $this->file = $this->directory.'/sent.ndjson';

        self::connection()->executeStatement('DELETE FROM shift_notifications');
        self::remove($this->directory);
    }

    protected function tearDown(): void
    {
        self::remove($this->directory);
    }

    public function testSendsPendingNotificationsAndMarksThemAsSent(): void
    {
        $first = self::notification(employeeId: 'employee-1');
        $second = self::notification(employeeId: 'employee-2');
        self::record($this->store, $first);
        self::record($this->store, $second);

        $tester = self::executeCommand(['--older-than' => '0']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Sent 2 notification(s), 0 failed.', $tester->getDisplay());

        $lines = file($this->file, FILE_IGNORE_NEW_LINES);

        self::assertIsArray($lines);
        self::assertCount(2, $lines);
        self::assertEqualsCanonicalizing(
            [$first->id(), $second->id()],
            array_map(static fn (string $line): mixed => self::decode($line)['id'], $lines),
        );
        self::assertNotNull(self::sentAt($first->id()));
        self::assertNotNull(self::sentAt($second->id()));
    }

    public function testReportsAndRetainsNotificationsThatCannotBeSent(): void
    {
        $notification = self::notification();
        self::record($this->store, $notification);

        // A file where the directory should be makes the sender fail.
        touch($this->directory);

        $tester = self::executeCommand(['--older-than' => '0']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Sent 0 notification(s), 1 failed.', $tester->getDisplay());
        self::assertNull(self::sentAt($notification->id()));
        self::assertSame(1, self::attempts($notification->id()));
    }

    public function testRejectsInvalidOptions(): void
    {
        $tester = self::executeCommand(['--limit' => '0']);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
    }

    /**
     * @param array<string, string> $input
     */
    private static function executeCommand(array $input): CommandTester
    {
        $kernel = self::$kernel;

        if (null === $kernel) {
            throw new \LogicException('Expected a booted kernel.');
        }

        $tester = new CommandTester(
            (new Application($kernel))->find('app:notifications:send'),
        );
        $tester->execute($input);

        return $tester;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $json): array
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new \LogicException('Expected a JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private static function record(NotificationStore $store, ShiftNotification $notification): void
    {
        $store->record($notification)->run()->fold(
            static fn (): never => throw new \LogicException('Expected the notification to be recorded.'),
            static fn (bool $recorded): null => $recorded
                ? null
                : throw new \LogicException('Expected the notification to be new.'),
        );
    }

    private static function sentAt(string $notificationId): mixed
    {
        return self::connection()->fetchOne(
            'SELECT sent_at FROM shift_notifications WHERE id = ?',
            [$notificationId],
        );
    }

    private static function attempts(string $notificationId): int
    {
        $attempts = self::connection()->fetchOne(
            'SELECT attempts FROM shift_notifications WHERE id = ?',
            [$notificationId],
        );

        return is_numeric($attempts) ? (int) $attempts : -1;
    }

    private static function notification(
        string $employeeId = 'employee-1',
        string $date = '2026-10-06',
        string $startTime = '09:00',
        string $endTime = '17:00',
    ): ShiftNotification {
        return new ShiftNotification(
            'shift-'.bin2hex(random_bytes(4)),
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

    private static function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);

        if (false === $entries) {
            return;
        }

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            self::remove($path.'/'.$entry);
        }

        rmdir($path);
    }
}
