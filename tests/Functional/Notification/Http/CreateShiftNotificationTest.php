<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notification\Http;

use App\Notification\Domain\ShiftNotification;
use App\Shift\Domain\Port\In\CreateShiftRequest;
use App\Shift\Domain\Port\Out\Shift;
use App\Shift\Infrastructure\Event\Emitter\ShiftPersisted;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class CreateShiftNotificationTest extends WebTestCase
{
    public function testRecordsAndSendsANotificationForACreatedShift(): void
    {
        $employeeId = 'employee-'.bin2hex(random_bytes(4));
        $client = self::createClient();

        $client->request('POST', '/shifts', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'employee_id' => $employeeId,
            'date' => '2026-12-23',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'country_code' => 'LT',
        ], JSON_THROW_ON_ERROR));

        self::assertSame(201, $client->getResponse()->getStatusCode());

        $shift = self::shiftFromResponse($client->getResponse()->getContent(), $employeeId);
        $notification = ShiftNotification::forShift($shift);

        $row = self::connection()->fetchAssociative(
            'SELECT employee_id, date, start_time, end_time, sent_at FROM shift_notifications WHERE id = ?',
            [$notification->id()],
        );

        self::assertIsArray($row);
        self::assertSame($employeeId, $row['employee_id']);
        self::assertSame('2026-12-23', $row['date']);
        self::assertSame('09:00:00', $row['start_time']);
        self::assertSame('17:00:00', $row['end_time']);
        self::assertNotNull($row['sent_at']);

        self::assertSame(1, self::linesFor($notification->id()));
    }

    public function testDoesNotNotifyTwiceWhenTheEventIsDeliveredAgain(): void
    {
        $employeeId = 'employee-'.bin2hex(random_bytes(4));
        $client = self::createClient();

        $client->request('POST', '/shifts', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'employee_id' => $employeeId,
            'date' => '2026-12-23',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'country_code' => 'LT',
        ], JSON_THROW_ON_ERROR));

        self::assertSame(201, $client->getResponse()->getStatusCode());

        $shift = self::shiftFromResponse($client->getResponse()->getContent(), $employeeId);
        $notification = ShiftNotification::forShift($shift);
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);

        if (!$dispatcher instanceof EventDispatcherInterface) {
            throw new \LogicException('Expected the event dispatcher service.');
        }

        $dispatcher->dispatch(new ShiftPersisted($shift));

        self::assertSame(1, self::rowCount(
            'SELECT COUNT(*) FROM shift_notifications WHERE id = ?',
            $notification->id(),
        ));
        self::assertSame(1, self::linesFor($notification->id()));
    }

    public function testDoesNotRecordANotificationForARejectedShift(): void
    {
        $employeeId = 'employee-'.bin2hex(random_bytes(4));
        $client = self::createClient();

        $client->request('POST', '/shifts', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'employee_id' => $employeeId,
            'date' => '2026-12-23',
            'start_time' => '17:00',
            'end_time' => '09:00',
            'country_code' => 'LT',
        ], JSON_THROW_ON_ERROR));

        self::assertSame(400, $client->getResponse()->getStatusCode());

        self::assertSame(0, self::rowCount(
            'SELECT COUNT(*) FROM shift_notifications WHERE employee_id = ?',
            $employeeId,
        ));
    }

    private static function shiftFromResponse(string|false $content, string $employeeId): Shift
    {
        if (false === $content) {
            throw new \LogicException('Expected a response body.');
        }

        $body = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($body) || !is_string($body['id'] ?? null) || !is_string($body['date'] ?? null)
            || !is_string($body['start_time'] ?? null) || !is_string($body['end_time'] ?? null)) {
            throw new \LogicException('Expected the created shift in the response.');
        }

        return new Shift(
            $body['id'],
            $employeeId,
            self::date($body['date']),
            self::time($body['start_time']),
            self::time($body['end_time']),
            'LT',
        );
    }

    private static function rowCount(string $sql, string $parameter): int
    {
        $count = self::connection()->fetchOne($sql, [$parameter]);

        return is_numeric($count) ? (int) $count : -1;
    }

    private static function linesFor(string $notificationId): int
    {
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        if (!is_string($projectDir)) {
            throw new \LogicException('Expected the project directory.');
        }

        $contents = @file_get_contents($projectDir.'/var/notifications/sent.ndjson');

        if (false === $contents) {
            return 0;
        }

        return substr_count($contents, $notificationId);
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
}
