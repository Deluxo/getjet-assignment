<?php

declare(strict_types=1);

namespace App\Shift\Application;

use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Shared\Functional\TaskEither;
use App\Shift\Domain\Port\In\CreateShift;
use App\Shift\Domain\Port\In\CreateShiftRequest;
use App\Shift\Domain\Port\Out\CreatedShift;
use App\Shift\Domain\Port\Out\ShiftErrors;

final class CreateShiftHandler implements CreateShift
{
    /**
     * @return TaskEither<ShiftErrors, CreatedShift>
     */
    public function invoke(CreateShiftRequest $request): TaskEither
    {
        return TaskEither::defer(static function () use ($request): Either {
            $errors = self::validate($request);

            if ($errors !== []) {
                return Either::Left(new ShiftErrors($errors));
            }

            return Either::Left(new ShiftErrors([new NotImplementedError()]));
        });
    }

    /**
     * @return list<Error>
     */
    private static function validate(CreateShiftRequest $request): array
    {
        $errors = [];

        if ($request->employeeId === '') {
            $errors[] = new FieldError('employee_id', 'must not be empty.');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->date)) {
            $errors[] = new FieldError('date', 'must be in YYYY-MM-DD format.');
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $request->startTime)) {
            $errors[] = new FieldError('start_time', 'must be in HH:mm format.');
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $request->endTime)) {
            $errors[] = new FieldError('end_time', 'must be in HH:mm format.');
        }

        if ($request->countryCode !== '' && $request->countryCode !== 'LT') {
            $errors[] = new FieldError('country_code', sprintf('"%s" is not supported.', $request->countryCode));
        }

        return $errors;
    }
}
