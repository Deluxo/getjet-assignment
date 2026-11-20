<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Application;

use App\Notification\Application\SendNotification;
use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Tests\Unit\Notification\Application\Support\FakeNotificationSender;
use App\Tests\Unit\Notification\Application\Support\FakeNotificationStore;
use App\Tests\Unit\Notification\Application\Support\NotificationFixtures;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class SendNotificationTest extends TestCase
{
    use NotificationFixtures;

    public function testSendsAndMarksTheNotificationAsSent(): void
    {
        $store = new FakeNotificationStore();
        $sender = new FakeNotificationSender();
        $notification = self::notification();

        $result = self::send($store, $sender)->handle($notification)->run();

        self::assertTrue($result->isRight());
        self::assertSame([$notification], $sender->sent);
        self::assertSame([$notification->id()], $store->sent);
        self::assertSame([], $store->failed);
    }

    public function testRecordsTheFailureAndReturnsIt(): void
    {
        $error = new Error('notification_sender', 'The notification could not be written.');
        $store = new FakeNotificationStore();
        $sender = new FakeNotificationSender($error);
        $notification = self::notification();

        $result = self::send($store, $sender)->handle($notification)->run();

        self::assertSame($error, self::left($result));
        self::assertSame([$notification->id() => $error], $store->failed);
        self::assertSame([], $store->sent);
    }

    public function testReturnsTheBookkeepingErrorWhenMarkingAsSentFails(): void
    {
        $error = new Error('notification_store', 'The notification state could not be updated.');
        $store = new FakeNotificationStore(markSentError: $error);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $result = self::send($store, new FakeNotificationSender(), $logger)
            ->handle(self::notification())
            ->run();

        self::assertSame($error, self::left($result));
    }

    public function testKeepsTheSendErrorWhenRecordingItsFailureAlsoFails(): void
    {
        $sendError = new Error('notification_sender', 'The notification could not be written.');
        $bookkeepingError = new Error('notification_store', 'The notification state could not be updated.');
        $store = new FakeNotificationStore(markFailedError: $bookkeepingError);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $result = self::send($store, new FakeNotificationSender($sendError), $logger)
            ->handle(self::notification())
            ->run();

        self::assertSame($sendError, self::left($result));
    }

    private static function send(
        FakeNotificationStore $store,
        FakeNotificationSender $sender,
        ?LoggerInterface $logger = null,
    ): SendNotification {
        return new SendNotification($sender, $store, $logger ?? new NullLogger());
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
}
