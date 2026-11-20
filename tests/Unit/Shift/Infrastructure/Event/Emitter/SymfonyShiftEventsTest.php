<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Infrastructure\Event\Emitter;

use App\Shift\Domain\Port\In\CreateShiftRequest;
use App\Shift\Domain\Port\Out\Shift;
use App\Shift\Infrastructure\Event\Emitter\ShiftPersisted;
use App\Shift\Infrastructure\Event\Emitter\SymfonyShiftEvents;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class SymfonyShiftEventsTest extends TestCase
{
    public function testDispatchesShiftPersistedWithTheShift(): void
    {
        $dispatcher = new EventDispatcher();
        $events = new SymfonyShiftEvents($dispatcher, new NullLogger());
        $shift = self::shift();
        $published = [];

        $dispatcher->addListener(
            ShiftPersisted::class,
            static function (ShiftPersisted $event) use (&$published): void {
                $published[] = $event->shift;
            },
        );

        $events->shiftPersisted($shift);

        self::assertSame([$shift], $published);
    }

    public function testSwallowsListenerFailuresAndLogsThem(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(self::stringContains('ShiftPersisted'));

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ShiftPersisted::class, static function (): void {
            throw new \RuntimeException('boom');
        });

        (new SymfonyShiftEvents($dispatcher, $logger))->shiftPersisted(self::shift());

        self::addToAssertionCount(1);
    }

    private static function shift(): Shift
    {
        return Shift::create(
            'employee-1',
            self::date('2026-10-06'),
            self::time('09:00'),
            self::time('17:00'),
            'LT',
        );
    }

    private static function date(string $value): \DateTimeImmutable
    {
        $parsed = CreateShiftRequest::parseDate($value);

        if (null === $parsed) {
            throw new \LogicException(sprintf('Cannot parse "%s".', $value));
        }

        return $parsed;
    }

    private static function time(string $value): \DateTimeImmutable
    {
        $parsed = CreateShiftRequest::parseTime($value);

        if (null === $parsed) {
            throw new \LogicException(sprintf('Cannot parse "%s".', $value));
        }

        return $parsed;
    }
}
