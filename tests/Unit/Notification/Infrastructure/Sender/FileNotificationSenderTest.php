<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Infrastructure\Sender;

use App\Notification\Infrastructure\Sender\FileNotificationSender;
use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Tests\Unit\Notification\Application\Support\NotificationFixtures;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class FileNotificationSenderTest extends TestCase
{
    use NotificationFixtures;

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/shift-notifications-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        self::remove($this->directory);
    }

    public function testWritesOneJsonLineAndCreatesTheDirectory(): void
    {
        $file = $this->directory.'/nested/sent.ndjson';
        $notification = self::notification();

        $result = (new FileNotificationSender($file, new NullLogger()))->send($notification)->run();

        self::assertTrue($result->isRight());
        self::assertFileExists($file);

        $lines = file($file, FILE_IGNORE_NEW_LINES);

        self::assertIsArray($lines);
        self::assertCount(1, $lines);
        self::assertSame($notification->toJson(), json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR));
    }

    public function testAppendsToAnExistingFile(): void
    {
        $file = $this->directory.'/sent.ndjson';
        $sender = new FileNotificationSender($file, new NullLogger());

        $sender->send(self::notification(employeeId: 'employee-1'))->run();
        $sender->send(self::notification(employeeId: 'employee-2'))->run();

        $lines = file($file, FILE_IGNORE_NEW_LINES);

        self::assertIsArray($lines);
        self::assertCount(2, $lines);
        self::assertSame('employee-1', self::decode($lines[0])['employee_id']);
        self::assertSame('employee-2', self::decode($lines[1])['employee_id']);
    }

    public function testReturnsAnErrorWhenTheDirectoryCannotBeCreated(): void
    {
        mkdir($this->directory, 0o775, true);
        $blocker = $this->directory.'/blocked';
        touch($blocker);

        $result = (new FileNotificationSender($blocker.'/sent.ndjson', new NullLogger()))
            ->send(self::notification())
            ->run();

        self::assertEquals(
            new Error('notification_sender', 'The notification directory could not be created.'),
            self::left($result),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $json): array
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new \LogicException('Expected a JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @template E
     * @template T
     *
     * @param Either<E, T> $either
     */
    private static function left(Either $either): mixed
    {
        return $either->fold(
            static fn (mixed $error): mixed => $error,
            static fn (): never => throw new \LogicException('Expected a left value.'),
        );
    }

    private static function remove(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);

        if (false === $entries) {
            return;
        }

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $directory.'/'.$entry;

            if (is_dir($path)) {
                self::remove($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
