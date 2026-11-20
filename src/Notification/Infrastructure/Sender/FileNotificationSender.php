<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Sender;

use App\Notification\Domain\Port\Out\NotificationSender;
use App\Notification\Domain\ShiftNotification;
use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Shared\Functional\TaskEither;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Appends one JSON line per sent notification. The file is the delivery log:
 * a notification that never reaches it stays pending for the cron retry.
 */
final readonly class FileNotificationSender implements NotificationSender
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/notifications/sent.ndjson')]
        private string $file,
        private LoggerInterface $logger,
    ) {
    }

    public function send(ShiftNotification $notification): TaskEither
    {
        return TaskEither::defer(fn () => $this->write($notification));
    }

    /**
     * @return Either<Error, null>
     */
    private function write(ShiftNotification $notification): Either
    {
        try {
            $line = json_encode($notification->toJson(), JSON_THROW_ON_ERROR).PHP_EOL;
        } catch (\JsonException $exception) {
            return $this->failure($notification, 'The notification could not be encoded.', $exception);
        }

        $directory = \dirname($this->file);

        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            return $this->failure($notification, 'The notification directory could not be created.');
        }

        if (false === @file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX)) {
            return $this->failure($notification, 'The notification could not be written.');
        }

        return Either::Right(null);
    }

    /**
     * @return Either<Error, null>
     */
    private function failure(ShiftNotification $notification, string $message, ?\Throwable $exception = null): Either
    {
        $this->logger->warning($message, [
            'notification_id' => $notification->id(),
            'file' => $this->file,
            'exception' => $exception,
        ]);

        return Either::Left(new Error('notification_sender', $message));
    }
}
