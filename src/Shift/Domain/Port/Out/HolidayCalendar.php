<?php

declare(strict_types=1);

namespace App\Shift\Domain\Port\Out;

use App\Shared\Error;
use App\Shared\Functional\TaskEither;

interface HolidayCalendar
{
    /**
     * @return TaskEither<Error, bool> Left: the calendar could not be consulted.
     */
    public function isPublicHoliday(string $countryCode, \DateTimeImmutable $date): TaskEither;
}
