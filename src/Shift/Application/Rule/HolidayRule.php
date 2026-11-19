<?php

declare(strict_types=1);

namespace App\Shift\Application\Rule;

use App\Shared\Error;
use App\Shared\Functional\TaskEither;
use App\Shared\Validation\ValidationRule;
use App\Shift\Domain\Port\In\CreateShiftRequest;
use App\Shift\Domain\Port\Out\HolidayCalendar;

/**
 * @implements ValidationRule<CreateShiftRequest>
 */
final readonly class HolidayRule implements ValidationRule
{
    public function __construct(private HolidayCalendar $holidays)
    {
    }

    /**
     * @param CreateShiftRequest $subject
     *
     * @return TaskEither<Error, CreateShiftRequest>
     */
    public function apply(mixed $subject): TaskEither
    {
        $date = self::parseDate($subject->date);

        if (null === $date) {
            return TaskEither::left(Error::forField('date', 'is not a valid date.'));
        }

        return $this->holidays->isPublicHoliday($subject->countryCode, $date)
            ->flatMap(static fn (bool $isHoliday): TaskEither => $isHoliday
                ? TaskEither::left(Error::forField('date', 'is a public holiday.'))
                : TaskEither::right($subject));
    }

    private static function parseDate(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (false === $date) {
            return null;
        }

        $errors = \DateTimeImmutable::getLastErrors();

        if (false !== $errors && (0 < $errors['warning_count'] || 0 < $errors['error_count'])) {
            return null;
        }

        return $date;
    }
}
