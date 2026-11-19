<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Application;

use App\Shared\Functional\Either;
use App\Shift\Application\CreateShiftHandler;
use App\Shift\Domain\Port\In\CreateShiftRequest;
use App\Shift\Domain\Port\Out\ShiftErrors;
use PHPUnit\Framework\TestCase;

final class CreateShiftHandlerTest extends TestCase
{
    public function testValidateReturnsFieldErrorsForInvalidFields(): void
    {
        $request = new CreateShiftRequest(
            employeeId: '',
            date: '06-10-2026',
            startTime: '9:00',
            endTime: '17:00',
            countryCode: 'DE',
        );

        $result = (new CreateShiftHandler())->invoke($request)->run();

        self::assertTrue($result->isLeft());

        /** @var ShiftErrors $errors */
        $errors = $result->fold(
            onLeft: static fn (ShiftErrors $value): ShiftErrors => $value,
            onRight: static fn (): never => throw new \LogicException('Expected a left value.'),
        );

        self::assertSame(
            ['employee_id', 'date', 'start_time', 'country_code'],
            array_map(static fn ($error) => $error->code(), $errors->errors()),
        );
    }
}
