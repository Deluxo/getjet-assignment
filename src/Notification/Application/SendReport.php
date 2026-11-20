<?php

declare(strict_types=1);

namespace App\Notification\Application;

final readonly class SendReport
{
    public function __construct(
        public int $sent,
        public int $failed,
    ) {
    }
}
