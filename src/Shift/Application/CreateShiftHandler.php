<?php

declare(strict_types=1);

namespace App\Shift\Application;

use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Shared\Functional\Task;
use App\Shift\Domain\Port\In\CreateShift;

final class CreateShiftHandler implements CreateShift
{
    /**
     * @return Task<Either<list<Error>, string>>
     */
    public function create(string $employeeId, string $date, string $startTime, string $endTime, string $countryCode): Task
    {
        return Task::of(static function () use ($employeeId, $date, $startTime, $endTime, $countryCode): Either {
            $errors = self::validate($employeeId, $date, $startTime, $endTime, $countryCode);

            if ($errors !== []) {
                return Either::Left($errors);
            }

            return Either::Left([new NotImplementedError()]);
        });
    }

    /**
     * @return list<Error>
     */
    private static function validate(
        string $employeeId,
        string $date,
        string $startTime,
        string $endTime,
        string $countryCode,
    ): array {
        $errors = [];

        if ($employeeId === '') {
            $errors[] = new FieldError('employee_id', 'must not be empty.');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errors[] = new FieldError('date', 'must be in YYYY-MM-DD format.');
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $startTime)) {
            $errors[] = new FieldError('start_time', 'must be in HH:mm format.');
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            $errors[] = new FieldError('end_time', 'must be in HH:mm format.');
        }

        if ($countryCode !== '' && $countryCode !== 'LT') {
            $errors[] = new FieldError('country_code', sprintf('"%s" is not supported.', $countryCode));
        }

        return $errors;
    }
}
