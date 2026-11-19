<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Application\Support;

use App\Shared\Error;
use App\Shared\Functional\TaskEither;
use App\Shift\Domain\Port\Out\HolidayCalendar;

final class FakeHolidayCalendar implements HolidayCalendar
{
    public int $calls = 0;

    public function __construct(private readonly bool|Error $result)
    {
    }

    /**
     * @return TaskEither<Error, bool>
     */
    public function isPublicHoliday(string $countryCode, \DateTimeImmutable $date): TaskEither
    {
        ++$this->calls;

        return $this->result instanceof Error
            ? TaskEither::left($this->result)
            : TaskEither::right($this->result);
    }
}
