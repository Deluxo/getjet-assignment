<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Application;

use App\Notification\Application\SendNotification;
use App\Notification\Application\SendPendingNotifications;
use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Tests\Unit\Notification\Application\Support\FakeNotificationSender;
use App\Tests\Unit\Notification\Application\Support\FakeNotificationStore;
use App\Tests\Unit\Notification\Application\Support\NotificationFixtures;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class SendPendingNotificationsTest extends TestCase
{
    use NotificationFixtures;

    public function testSendsEveryPendingNotification(): void
    {
        $first = self::notification(employeeId: 'employee-1');
        $second = self::notification(employeeId: 'employee-2');
        $store = new FakeNotificationStore();
        $store->pending = [$first, $second];
        $sender = new FakeNotificationSender();

        $report = self::right(self::send($store, $sender)->handle(new \DateTimeImmutable(), 10, 5)->run());

        self::assertSame(2, $report->sent);
        self::assertSame(0, $report->failed);
        self::assertSame([$first, $second], $sender->sent);
        self::assertSame([$first->id(), $second->id()], $store->sent);
    }

    public function testPassesTheLimitToTheStore(): void
    {
        $store = new FakeNotificationStore();
        $store->pending = [self::notification()];

        self::right(self::send($store, new FakeNotificationSender())->handle(new \DateTimeImmutable(), 3, 5)->run());

        self::assertSame(3, $store->lastLimit);
    }

    public function testOneFailingNotificationDoesNotStopTheBatch(): void
    {
        $first = self::notification(employeeId: 'employee-1');
        $second = self::notification(employeeId: 'employee-2');
        $third = self::notification(employeeId: 'employee-3');
        $store = new FakeNotificationStore();
        $store->pending = [$first, $second, $third];
        $sender = new FakeNotificationSender();
        $sender->errors[$second->id()] = new Error('notification_sender', 'The notification could not be written.');

        $report = self::right(self::send($store, $sender)->handle(new \DateTimeImmutable(), 10, 5)->run());

        self::assertSame(2, $report->sent);
        self::assertSame(1, $report->failed);
        self::assertSame([$first, $third], $sender->sent);
        self::assertArrayHasKey($second->id(), $store->failed);
    }

    public function testReturnsTheStoreFailure(): void
    {
        $error = new Error('notification_store', 'Pending notifications could not be loaded.');
        $store = new FakeNotificationStore(pendingError: $error);

        $result = self::send($store, new FakeNotificationSender())
            ->handle(new \DateTimeImmutable(), 10, 5)
            ->run();

        self::assertSame($error, self::left($result));
    }

    private static function send(FakeNotificationStore $store, FakeNotificationSender $sender): SendPendingNotifications
    {
        return new SendPendingNotifications($store, new SendNotification($sender, $store, new NullLogger()));
    }

    /**
     * @template E
     * @template T
     *
     * @param Either<E, T> $either
     */
    private static function left(Either $either): mixed
    {
        return $either->fold(
            static fn (mixed $error): mixed => $error,
            static fn (): never => throw new \LogicException('Expected a left value.'),
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
            static fn (Error $error): never => throw new \LogicException($error->message()),
            static fn (mixed $value): mixed => $value,
        );
    }
}
