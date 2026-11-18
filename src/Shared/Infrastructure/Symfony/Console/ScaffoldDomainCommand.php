<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:scaffold:domain',
    description: 'Create the directory skeleton for a domain under src/',
)]
final class ScaffoldDomainCommand extends Command
{
    private const DIRECTORIES = [
        'Domain/Model',
        'Domain/Port/In',
        'Domain/Port/Out',
        'Domain/Service',
        'Application',
        'Infrastructure/Persistence/Mapping',
        'Infrastructure/Api',
        'Infrastructure/Http/Controller',
        'Infrastructure/Cli',
    ];

    public function __construct(private readonly string $srcDir)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Domain name in PascalCase (e.g. Shift, LaborCost)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = trim((string) $input->getArgument('name'));

        if (1 !== preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
            $output->writeln(sprintf(
                '<error>Invalid domain name "%s". Expected PascalCase, e.g. Shift or LaborCost.</error>',
                $name,
            ));

            return Command::INVALID;
        }

        if (!is_dir($this->srcDir)) {
            $output->writeln(sprintf('<error>Source directory "%s" does not exist.</error>', $this->srcDir));

            return Command::FAILURE;
        }

        try {
            $this->scaffold($name, $output);
        } catch (\RuntimeException $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function scaffold(string $name, OutputInterface $output): void
    {
        foreach (self::DIRECTORIES as $relativePath) {
            $directory = $this->srcDir.'/'.$name.'/'.$relativePath;
            $displayPath = 'src/'.$name.'/'.$relativePath;

            if (is_dir($directory)) {
                $output->writeln(sprintf('exists   %s', $displayPath));
            } else {
                $this->createDirectory($directory);
                $output->writeln(sprintf('created  %s', $displayPath));
            }
        }
    }

    private function createDirectory(string $directory): void
    {
        if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create directory "%s".', $directory));
        }
    }
}
