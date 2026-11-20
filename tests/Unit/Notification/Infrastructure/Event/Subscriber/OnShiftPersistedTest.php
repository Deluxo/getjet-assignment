<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Infrastructure\Event\Subscriber;

use App\Notification\Application\RecordNotification;
use App\Notification\Application\SendNotification;
use App\Notification\Domain\ShiftNotification;
use App\Notification\Infrastructure\Event\Subscriber\OnShiftPersisted;
use App\Shared\Error;
use App\Shift\Infrastructure\Event\Emitter\ShiftPersisted;
use App\Tests\Unit\Notification\Application\Support\FakeNotificationSender;
use App\Tests\Unit\Notification\Application\Support\FakeNotificationStore;
use App\Tests\Unit\Notification\Application\Support\NotificationFixtures;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class OnShiftPersistedTest extends TestCase
{
    use NotificationFixtures;

    public function testRecordsAndSendsANotificationForThePersistedShift(): void
    {
        $store = new FakeNotificationStore();
        $sender = new FakeNotificationSender();
        $shift = self::shift();

        self::subscriber($store, $sender)(new ShiftPersisted($shift));

        self::assertEquals([ShiftNotification::forShift($shift)], $store->recorded);
        self::assertEquals([ShiftNotification::forShift($shift)], $sender->sent);
    }

    public function testSwallowsAndLogsRecordFailures(): void
    {
        $error = new Error('notification_store', 'The notification could not be recorded.');
        $store = new FakeNotificationStore(recordError: $error);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        self::subscriber($store, new FakeNotificationSender(), $logger)(new ShiftPersisted(self::shift()));

        self::assertSame([], $store->recorded);
    }

    public function testDoesNothingWhenNotificationsAreDisabled(): void
    {
        $store = new FakeNotificationStore();
        $sender = new FakeNotificationSender();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        self::subscriber($store, $sender, $logger, enabled: false)(new ShiftPersisted(self::shift()));

        self::assertSame([], $store->recorded);
        self::assertSame([], $sender->sent);
    }

    private static function subscriber(
        FakeNotificationStore $store,
        FakeNotificationSender $sender,
        ?LoggerInterface $logger = null,
        bool $enabled = true,
    ): OnShiftPersisted {
        return new OnShiftPersisted(
            new RecordNotification($store, new SendNotification($sender, $store, new NullLogger())),
            $logger ?? new NullLogger(),
            $enabled,
        );
    }
}
