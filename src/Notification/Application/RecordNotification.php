<?php

declare(strict_types=1);

namespace App\Notification\Application;

use App\Notification\Domain\Port\Out\NotificationStore;
use App\Notification\Domain\ShiftNotification;
use App\Shared\Error;
use App\Shared\Functional\TaskEither;

final readonly class RecordNotification
{
    public function __construct(
        private NotificationStore $store,
        private SendNotification $send,
    ) {
    }

    /**
     * Records the notification and makes the first send attempt. When the
     * notification is already known, nothing happens.
     *
     * @return TaskEither<Error, null>
     */
    public function handle(ShiftNotification $notification): TaskEither
    {
        return $this->store->record($notification)->flatMap(
            fn (bool $recorded): TaskEither => $recorded
                ? $this->send->handle($notification)
                : TaskEither::right(null),
        );
    }
}
