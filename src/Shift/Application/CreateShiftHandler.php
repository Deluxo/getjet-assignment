<?php

declare(strict_types=1);

namespace App\Shift\Application;

use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Shared\Functional\TaskEither;
use App\Shift\Domain\Port\In\CreateShift;
use App\Shift\Domain\Port\In\CreateShiftRequest;
use App\Shift\Domain\Port\Out\CreatedShift;

final class CreateShiftHandler implements CreateShift
{
    /**
     * @return TaskEither<list<Error>, CreatedShift>
     */
    public function invoke(CreateShiftRequest $request): TaskEither
    {
        return TaskEither::defer(static fn (): Either => Either::Left(self::errors($request)));
    }

    /**
     * @return list<Error>
     */
    private static function errors(CreateShiftRequest $request): array
    {
        $errors = self::validate($request);

        return $errors !== [] ? $errors : [new Error('not_implemented', 'Not implemented.')];
    }

    /**
     * @return list<Error>
     */
    private static function validate(CreateShiftRequest $request): array
    {
        $errors = [];

        if ($request->employeeId === '') {
            $errors[] = Error::forField('employee_id', 'must not be empty.');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->date)) {
            $errors[] = Error::forField('date', 'must be in YYYY-MM-DD format.');
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $request->startTime)) {
            $errors[] = Error::forField('start_time', 'must be in HH:mm format.');
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $request->endTime)) {
            $errors[] = Error::forField('end_time', 'must be in HH:mm format.');
        }

        if ($request->countryCode !== '' && $request->countryCode !== 'LT') {
            $errors[] = Error::forField('country_code', sprintf('"%s" is not supported.', $request->countryCode));
        }

        return $errors;
    }
}
