<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Functional\Laws;

use App\Shared\Functional\Laws\Identity;
use PHPUnit\Framework\TestCase;

final class IdentityTest extends TestCase
{
    public function testOfReturnsAThunkEvaluatingToTheValue(): void
    {
        self::assertSame(42, (Identity::of(42))());
    }

    public function testOfReturnsTheSameObject(): void
    {
        $value = new \stdClass();

        self::assertSame($value, (Identity::of($value))());
    }

    public function testThunkCanRunMultipleTimes(): void
    {
        $value = new \stdClass();
        $thunk = Identity::of($value);

        self::assertSame([$value, $value], [$thunk(), $thunk()]);
    }
}
