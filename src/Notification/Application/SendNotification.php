<?php

declare(strict_types=1);

namespace App\Notification\Application;

use App\Notification\Domain\Port\Out\NotificationSender;
use App\Notification\Domain\Port\Out\NotificationStore;
use App\Notification\Domain\ShiftNotification;
use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Shared\Functional\TaskEither;
use Psr\Log\LoggerInterface;

/**
 * One send attempt for one notification. A failure is recorded on the row so
 * the cron retry can find it; it never throws.
 */
final readonly class SendNotification
{
    public function __construct(
        private NotificationSender $sender,
        private NotificationStore $store,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return TaskEither<Error, null>
     */
    public function handle(ShiftNotification $notification): TaskEither
    {
        return TaskEither::defer(fn () => $this->attempt($notification));
    }

    /**
     * @return Either<Error, null>
     */
    private function attempt(ShiftNotification $notification): Either
    {
        return $this->sender->send($notification)->run()->fold(
            fn (Error $error): Either => $this->recordFailure($notification, $error),
            fn (): Either => $this->markSent($notification),
        );
    }

    /**
     * @return Either<Error, null>
     */
    private function recordFailure(ShiftNotification $notification, Error $error): Either
    {
        $this->store->markFailed($notification->id(), $error)->run()->fold(
            function (Error $bookkeepingError) use ($notification): void {
                $this->logger->error('Could not record a notification failure.', [
                    'notification_id' => $notification->id(),
                    'code' => $bookkeepingError->code(),
                    'message' => $bookkeepingError->message(),
                ]);
            },
            static fn (): null => null,
        );

        return Either::Left($error);
    }

    /**
     * @return Either<Error, null>
     */
    private function markSent(ShiftNotification $notification): Either
    {
        return $this->store->markSent($notification->id())->run()->fold(
            function (Error $error) use ($notification): Either {
                $this->logger->error('Could not mark a notification as sent.', [
                    'notification_id' => $notification->id(),
                    'code' => $error->code(),
                    'message' => $error->message(),
                ]);

                return Either::Left($error);
            },
            static fn (): Either => Either::Right(null),
        );
    }
}
