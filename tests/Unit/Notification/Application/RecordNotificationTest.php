<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Application;

use App\Notification\Application\RecordNotification;
use App\Notification\Application\SendNotification;
use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Tests\Unit\Notification\Application\Support\FakeNotificationSender;
use App\Tests\Unit\Notification\Application\Support\FakeNotificationStore;
use App\Tests\Unit\Notification\Application\Support\NotificationFixtures;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class RecordNotificationTest extends TestCase
{
    use NotificationFixtures;

    public function testRecordsAndSendsANewNotification(): void
    {
        $store = new FakeNotificationStore();
        $sender = new FakeNotificationSender();
        $notification = self::notification();

        $result = self::record($store, $sender)->handle($notification)->run();

        self::assertTrue($result->isRight());
        self::assertSame([$notification], $store->recorded);
        self::assertSame([$notification], $sender->sent);
        self::assertSame([$notification->id()], $store->sent);
    }

    public function testDoesNothingForAnAlreadyRecordedNotification(): void
    {
        $store = new FakeNotificationStore(isNew: false);
        $sender = new FakeNotificationSender();
        $notification = self::notification();

        $result = self::record($store, $sender)->handle($notification)->run();

        self::assertTrue($result->isRight());
        self::assertSame([], $store->recorded);
        self::assertSame([], $sender->sent);
        self::assertSame([], $store->sent);
    }

    public function testDoesNotSendWhenTheNotificationCannotBeRecorded(): void
    {
        $error = new Error('notification_store', 'The notification could not be recorded.');
        $store = new FakeNotificationStore(recordError: $error);
        $sender = new FakeNotificationSender();

        $result = self::record($store, $sender)->handle(self::notification())->run();

        self::assertSame($error, self::left($result));
        self::assertSame([], $sender->sent);
    }

    public function testReturnsTheSendFailure(): void
    {
        $error = new Error('notification_sender', 'The notification could not be written.');
        $store = new FakeNotificationStore();
        $sender = new FakeNotificationSender($error);

        $result = self::record($store, $sender)->handle(self::notification())->run();

        self::assertSame($error, self::left($result));
        self::assertArrayHasKey(self::notification()->id(), $store->failed);
    }

    private static function record(FakeNotificationStore $store, FakeNotificationSender $sender): RecordNotification
    {
        return new RecordNotification($store, new SendNotification($sender, $store, new NullLogger()));
    }

    private static function left(Either $either): mixed
    {
        return $either->fold(
            static fn (mixed $error): mixed => $error,
            static fn (): never => throw new \LogicException('Expected a left value.'),
        );
    }
}
