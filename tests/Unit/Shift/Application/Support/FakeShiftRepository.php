<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Application\Support;

use App\Shared\Error;
use App\Shared\Functional\TaskEither;
use App\Shift\Domain\Port\Out\Shift;
use App\Shift\Domain\Port\Out\ShiftRepository;

final class FakeShiftRepository implements ShiftRepository
{
    /**
     * @var list<Shift>
     */
    public array $saved = [];

    public int $createCalls = 0;

    public function __construct(private readonly ?Error $error = null) {}

    /**
     * @return TaskEither<Error, Shift>
     */
    public function create(Shift $shift): TaskEither
    {
        ++$this->createCalls;

        if (null !== $this->error) {
            return TaskEither::left($this->error);
        }

        $this->saved[] = $shift;

        return TaskEither::right($shift);
    }
}
