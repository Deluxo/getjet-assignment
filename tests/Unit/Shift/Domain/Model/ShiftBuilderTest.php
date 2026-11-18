<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Domain\Model;

use App\Shared\Functional\Task;
use App\Shift\Application\ShiftBuilder;
use PHPUnit\Framework\TestCase;

final class ShiftBuilderTest extends TestCase
{
    public function testStartsEmpty(): void
    {
        $builder = new ShiftBuilder();

        self::assertNull($builder->employeeId);
        self::assertNull($builder->date);
        self::assertNull($builder->startTime);
        self::assertNull($builder->endTime);
        self::assertNull($builder->countryCode);
    }

    public function testWithMethodsReturnANewBuilder(): void
    {
        $builder = new ShiftBuilder();
        $filled = $builder
            ->withEmployeeId('employee-123')
            ->withDate('2026-10-06')
            ->withStartTime('09:00')
            ->withEndTime('17:00')
            ->withCountryCode('LT');

        self::assertNotSame($builder, $filled);
        self::assertNull($builder->employeeId);
        self::assertSame('employee-123', $filled->employeeId);
        self::assertSame('2026-10-06', $filled->date);
        self::assertSame('09:00', $filled->startTime);
        self::assertSame('17:00', $filled->endTime);
        self::assertSame('LT', $filled->countryCode);
    }

    public function testEachWithMethodKeepsTheOtherValues(): void
    {
        $builder = (new ShiftBuilder())
            ->withEmployeeId('employee-123')
            ->withDate('2026-10-06')
            ->withStartTime('09:00')
            ->withEndTime('17:00')
            ->withCountryCode('LT')
            ->withEndTime('18:00');

        self::assertSame('employee-123', $builder->employeeId);
        self::assertSame('2026-10-06', $builder->date);
        self::assertSame('09:00', $builder->startTime);
        self::assertSame('18:00', $builder->endTime);
        self::assertSame('LT', $builder->countryCode);
    }

    public function testBuildReturnsALazyTask(): void
    {
        $builder = (new ShiftBuilder())->withEmployeeId('employee-123');

        self::assertInstanceOf(Task::class, $builder->build());
    }

    public function testBuildIsNotImplementedYet(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('not implemented yet');

        (new ShiftBuilder())->build()->run();
    }
}
