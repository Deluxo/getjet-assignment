<?php

declare(strict_types=1);

namespace App\Notification\Domain\Port\Out;

use App\Notification\Domain\ShiftNotification;
use App\Shared\Error;
use App\Shared\Functional\TaskEither;

interface NotificationStore
{
    /**
     * Stores the notification unless one with the same identity already exists.
     *
     * @return TaskEither<Error, bool> Right is true when the row was inserted.
     */
    public function record(ShiftNotification $notification): TaskEither;

    /**
     * Notifications recorded before the given moment that have not been sent
     * and did not exhaust their attempts, oldest first.
     *
     * @return TaskEither<Error, list<ShiftNotification>>
     */
    public function pending(\DateTimeImmutable $createdBefore, int $limit, int $maxAttempts): TaskEither;

    /**
     * @return TaskEither<Error, null>
     */
    public function markSent(string $notificationId): TaskEither;

    /**
     * @return TaskEither<Error, null>
     */
    public function markFailed(string $notificationId, Error $error): TaskEither;
}
