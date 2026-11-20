<?php

declare(strict_types=1);

namespace App\Shift\Infrastructure\Event\Emitter;

use App\Shift\Domain\Port\Out\Shift;
use App\Shift\Domain\Port\Out\ShiftEvents;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class SymfonyShiftEvents implements ShiftEvents
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private LoggerInterface $logger,
    ) {
    }

    public function shiftPersisted(Shift $shift): void
    {
        try {
            $this->dispatcher->dispatch(new ShiftPersisted($shift));
        } catch (\Throwable $exception) {
            // The shift is already committed; a broken subscriber must not fail
            // the request that created it.
            $this->logger->error('A listener failed while handling ShiftPersisted.', [
                'shift_id' => $shift->id,
                'exception' => $exception,
            ]);
        }
    }
}
