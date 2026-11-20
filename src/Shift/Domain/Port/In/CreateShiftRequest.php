<?php

declare(strict_types=1);

namespace App\Shift\Domain\Port\In;

use App\Shared\Error;
use App\Shared\Functional\Either;

final readonly class CreateShiftRequest
{
    public ?\DateTimeImmutable $date;
    public ?\DateTimeImmutable $startTime;
    public ?\DateTimeImmutable $endTime;

    public function __construct(
        public string $employeeId,
        string $date,
        string $startTime,
        string $endTime,
        public string $countryCode,
    ) {
        $this->date = self::parseDate($date);
        $this->startTime = self::parseTime($startTime);
        $this->endTime = self::parseTime($endTime);
    }

    /**
     * Promotes a request whose rules passed into a command with parsed values.
     * The Left branches are defensive: the rules reject unparsed input first.
     *
     * @return Either<Error, CreateShiftCommand>
     */
    public function toCommand(): Either
    {
        $date = $this->date;
        $startTime = $this->startTime;
        $endTime = $this->endTime;

        if (null === $date) {
            return Either::Left(Error::forField('date', 'is not a valid date.'));
        }

        if (null === $startTime) {
            return Either::Left(Error::forField('startTime', 'is not a valid time.'));
        }

        if (null === $endTime) {
            return Either::Left(Error::forField('endTime', 'is not a valid time.'));
        }

        return Either::Right(new CreateShiftCommand(
            $this->employeeId,
            $date,
            $startTime,
            $endTime,
            $this->countryCode,
        ));
    }

    public static function parseDate(string $value): ?\DateTimeImmutable
    {
        return self::parse($value, '!Y-m-d', 'Y-m-d');
    }

    public static function parseTime(string $value): ?\DateTimeImmutable
    {
        return self::parse($value, '!H:i', 'H:i');
    }

    private static function parse(string $value, string $format, string $canonical): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat($format, $value);

        if (false === $date) {
            return null;
        }

        $errors = \DateTimeImmutable::getLastErrors();

        if (false !== $errors && (0 < $errors['warning_count'] || 0 < $errors['error_count'])) {
            return null;
        }

        if ($date->format($canonical) !== $value) {
            return null;
        }

        return $date;
    }
}
