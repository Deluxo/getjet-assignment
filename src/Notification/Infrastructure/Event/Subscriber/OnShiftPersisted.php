<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Event\Subscriber;

use App\Notification\Application\RecordNotification;
use App\Notification\Domain\ShiftNotification;
use App\Shared\Error;
use App\Shift\Infrastructure\Event\Emitter\ShiftPersisted;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class OnShiftPersisted
{
    public function __construct(
        private RecordNotification $record,
        private LoggerInterface $logger,
        #[Autowire('%env(bool:SHIFT_NOTIFICATIONS_ENABLED)%')]
        private bool $enabled,
    ) {
    }

    #[AsEventListener]
    public function __invoke(ShiftPersisted $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $result = $this->record->handle(ShiftNotification::forShift($event->shift))->run();

        $result->fold(
            function (Error $error) use ($event): void {
                $this->logger->error('Could not record or send a shift notification.', [
                    'shift_id' => $event->shift->id,
                    'code' => $error->code(),
                    'message' => $error->message(),
                ]);
            },
            static fn (): null => null,
        );
    }
}
