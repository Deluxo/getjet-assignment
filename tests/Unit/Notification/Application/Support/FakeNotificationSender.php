<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Application\Support;

use App\Notification\Domain\Port\Out\NotificationSender;
use App\Notification\Domain\ShiftNotification;
use App\Shared\Error;
use App\Shared\Functional\TaskEither;

final class FakeNotificationSender implements NotificationSender
{
    /**
     * @var list<ShiftNotification>
     */
    public array $sent = [];

    /**
     * Per-notification failures, keyed by notification id.
     *
     * @var array<string, Error>
     */
    public array $errors = [];

    public function __construct(private readonly ?Error $error = null)
    {
    }

    public function send(ShiftNotification $notification): TaskEither
    {
        $error = $this->errors[$notification->id()] ?? $this->error;

        if (null !== $error) {
            return TaskEither::left($error);
        }

        $this->sent[] = $notification;

        return TaskEither::right(null);
    }
}
