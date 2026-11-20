<?php

declare(strict_types=1);

namespace App\Notification\Application;

use App\Notification\Domain\Port\Out\NotificationStore;
use App\Notification\Domain\ShiftNotification;
use App\Shared\Error;
use App\Shared\Functional\TaskEither;

/**
 * Drains the notification outbox: every attempt is independent, so one poison
 * row cannot stop the batch.
 */
final readonly class SendPendingNotifications
{
    public function __construct(
        private NotificationStore $store,
        private SendNotification $send,
    ) {
    }

    /**
     * @return TaskEither<Error, SendReport>
     */
    public function handle(\DateTimeImmutable $createdBefore, int $limit, int $maxAttempts): TaskEither
    {
        return $this->store->pending($createdBefore, $limit, $maxAttempts)->map($this->sendAll(...));
    }

    /**
     * @param list<ShiftNotification> $notifications
     */
    private function sendAll(array $notifications): SendReport
    {
        $sent = 0;
        $failed = 0;

        foreach ($notifications as $notification) {
            if ($this->send->handle($notification)->run()->isRight()) {
                ++$sent;
            } else {
                ++$failed;
            }
        }

        return new SendReport($sent, $failed);
    }
}
