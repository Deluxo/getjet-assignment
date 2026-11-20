<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Application\Support;

use App\Shift\Domain\Port\Out\Shift;
use App\Shift\Domain\Port\Out\ShiftEvents;

final class FakeShiftEvents implements ShiftEvents
{
    /**
     * @var list<Shift>
     */
    public array $published = [];

    public function shiftPersisted(Shift $shift): void
    {
        $this->published[] = $shift;
    }
}
