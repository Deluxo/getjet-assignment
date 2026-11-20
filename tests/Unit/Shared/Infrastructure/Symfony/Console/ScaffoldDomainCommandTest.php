<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Symfony\Console;

use App\Shared\Infrastructure\Symfony\Console\ScaffoldDomainCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ScaffoldDomainCommandTest extends TestCase
{
    private string $srcDir;

    protected function setUp(): void
    {
        $this->srcDir = sys_get_temp_dir().'/scaffold-domain-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->srcDir, 0775, true));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->srcDir);
    }

    public function testCreatesTheDomainSkeleton(): void
    {
        $exitCode = $this->tester()->execute(['name' => 'Shift']);

        self::assertSame(Command::SUCCESS, $exitCode);

        foreach ([
            'Domain/Model',
            'Domain/Port/In',
            'Domain/Port/Out',
            'Domain/Service',
            'Application',
            'Infrastructure/Persistence/Mapping',
            'Infrastructure/Api',
            'Infrastructure/Http/Controller',
            'Infrastructure/Cli',
        ] as $relativePath) {
            self::assertDirectoryExists($this->srcDir.'/Shift/'.$relativePath);
        }
    }

    public function testSecondRunIsIdempotent(): void
    {
        $first = $this->tester()->execute(['name' => 'Shift']);
        $second = $this->tester()->execute(['name' => 'Shift']);

        self::assertSame([Command::SUCCESS, Command::SUCCESS], [$first, $second]);
    }

    public function testRejectsNamesThatAreNotPascalCase(): void
    {
        $exitCode = $this->tester()->execute(['name' => '../Evil']);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertSame([], array_values(array_diff(scandir($this->srcDir) ?: [], ['.', '..'])));
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new ScaffoldDomainCommand($this->srcDir));
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
