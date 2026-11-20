<?php

declare(strict_types=1);

namespace App\Shift\Domain\Port\Out;

interface ShiftEvents
{
    /**
     * Announces that a shift has been stored. Publishing never fails: an
     * undeliverable event must not turn a created shift into an error.
     */
    public function shiftPersisted(Shift $shift): void;
}
