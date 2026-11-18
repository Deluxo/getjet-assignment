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

        foreach (['Domain', 'Application', 'Infrastructure/Symfony', 'Infrastructure/Doctrine'] as $relativePath) {
            self::assertDirectoryExists($this->srcDir.'/Shift/'.$relativePath);
            self::assertFileExists($this->srcDir.'/Shift/'.$relativePath.'/.gitkeep');
        }
    }

    public function testSecondRunChangesNothing(): void
    {
        $command = new ScaffoldDomainCommand($this->srcDir);
        $commandTester = new CommandTester($command);

        self::assertSame(Command::SUCCESS, $commandTester->execute(['name' => 'Shift']));

        $existingFile = $this->srcDir.'/Shift/Domain/Shift.php';
        file_put_contents($existingFile, '<?php // keep me');

        self::assertSame(Command::SUCCESS, $commandTester->execute(['name' => 'Shift']));

        self::assertSame('<?php // keep me', file_get_contents($existingFile));
        self::assertFileExists($this->srcDir.'/Shift/Domain/.gitkeep');
    }

    public function testDoesNotAddGitkeepToExistingNonEmptyDirectory(): void
    {
        $domainDir = $this->srcDir.'/Shift/Domain';
        self::assertTrue(mkdir($domainDir, 0775, true));
        file_put_contents($domainDir.'/Shift.php', '<?php // keep me');

        self::assertSame(Command::SUCCESS, $this->tester()->execute(['name' => 'Shift']));

        self::assertFileDoesNotExist($domainDir.'/.gitkeep');
        self::assertFileExists($domainDir.'/Shift.php');
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
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
