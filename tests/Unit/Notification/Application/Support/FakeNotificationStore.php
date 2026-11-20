<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Application\Support;

use App\Notification\Domain\Port\Out\NotificationStore;
use App\Notification\Domain\ShiftNotification;
use App\Shared\Error;
use App\Shared\Functional\TaskEither;

final class FakeNotificationStore implements NotificationStore
{
    /**
     * @var list<ShiftNotification>
     */
    public array $recorded = [];

    /**
     * @var list<string>
     */
    public array $sent = [];

    /**
     * @var array<string, Error>
     */
    public array $failed = [];

    /**
     * @var list<ShiftNotification>
     */
    public array $pending = [];

    public function __construct(
        private readonly bool $isNew = true,
        private readonly ?Error $recordError = null,
        private readonly ?Error $pendingError = null,
        private readonly ?Error $markSentError = null,
        private readonly ?Error $markFailedError = null,
    ) {
    }

    public function record(ShiftNotification $notification): TaskEither
    {
        if (null !== $this->recordError) {
            return TaskEither::left($this->recordError);
        }

        if ($this->isNew) {
            $this->recorded[] = $notification;
        }

        return TaskEither::right($this->isNew);
    }

    public function pending(\DateTimeImmutable $createdBefore, int $limit, int $maxAttempts): TaskEither
    {
        if (null !== $this->pendingError) {
            return TaskEither::left($this->pendingError);
        }

        return TaskEither::right(\array_slice($this->pending, 0, $limit));
    }

    public function markSent(string $notificationId): TaskEither
    {
        if (null !== $this->markSentError) {
            return TaskEither::left($this->markSentError);
        }

        $this->sent[] = $notificationId;

        return TaskEither::right(null);
    }

    public function markFailed(string $notificationId, Error $error): TaskEither
    {
        if (null !== $this->markFailedError) {
            return TaskEither::left($this->markFailedError);
        }

        $this->failed[$notificationId] = $error;

        return TaskEither::right(null);
    }
}
