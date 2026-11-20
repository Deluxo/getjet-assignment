<?php

declare(strict_types=1);

namespace App\Notification\Domain\Port\Out;

use App\Notification\Domain\ShiftNotification;
use App\Shared\Error;
use App\Shared\Functional\TaskEither;

interface NotificationSender
{
    /**
     * @return TaskEither<Error, null>
     */
    public function send(ShiftNotification $notification): TaskEither;
}
