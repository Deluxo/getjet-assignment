<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Application\Rule;

use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Shift\Application\Rule\EmployeeIdRule;
use App\Shift\Domain\Port\In\CreateShiftRequest;
use PHPUnit\Framework\TestCase;

final class EmployeeIdRuleTest extends TestCase
{
    public function testPassesANonEmptyEmployeeId(): void
    {
        $request = self::request('employee-123');

        self::assertSame($request, self::right((new EmployeeIdRule())->apply($request)->run()));
    }

    public function testRejectsAnEmptyEmployeeId(): void
    {
        $result = (new EmployeeIdRule())->apply(self::request(''))->run();

        self::assertEquals(
            Error::forField('employeeId', 'must not be empty.'),
            self::left($result),
        );
    }

    public function testRejectsABlankEmployeeId(): void
    {
        $result = (new EmployeeIdRule())->apply(self::request('   '))->run();

        self::assertEquals(
            Error::forField('employeeId', 'must not be empty.'),
            self::left($result),
        );
    }

    private static function request(string $employeeId): CreateShiftRequest
    {
        return new CreateShiftRequest(
            employeeId: $employeeId,
            date: '2026-10-06',
            startTime: '09:00',
            endTime: '17:00',
            countryCode: 'LT',
        );
    }

    /**
     * @template T
     *
     * @param Either<Error, T> $either
     *
     * @return Error
     */
    private static function left(Either $either): mixed
    {
        return $either->fold(
            static fn (mixed $error): mixed => $error,
            static fn (): never => throw new \LogicException('Expected a left value.'),
        );
    }

    /**
     * @template T
     *
     * @param Either<Error, T> $either
     *
     * @return T
     */
    private static function right(Either $either): mixed
    {
        return $either->fold(
            static fn (): never => throw new \LogicException('Expected a right value.'),
            static fn (mixed $value): mixed => $value,
        );
    }
}
