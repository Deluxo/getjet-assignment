<?php

declare(strict_types=1);

namespace App\Shift\Infrastructure\Event\Emitter;

use App\Shift\Domain\Port\Out\Shift;

final readonly class ShiftPersisted
{
    public function __construct(public Shift $shift)
    {
    }
}
