<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Console;

use App\Notification\Application\SendPendingNotifications;
use App\Notification\Application\SendReport;
use App\Shared\Error;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notifications:send',
    description: 'Sends pending shift notifications.',
)]
final class SendPendingNotificationsCommand extends Command
{
    public function __construct(
        private readonly SendPendingNotifications $send,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of notifications to send per run.', '100')
            ->addOption('max-attempts', null, InputOption::VALUE_REQUIRED, 'Skip notifications that failed this many times.', '5')
            ->addOption('older-than', null, InputOption::VALUE_REQUIRED, 'Only send notifications recorded at least this many seconds ago.', '60');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = self::integerOption($input, 'limit');
        $maxAttempts = self::integerOption($input, 'max-attempts');
        $olderThan = self::integerOption($input, 'older-than');

        if ($limit < 1 || $maxAttempts < 1 || $olderThan < 0) {
            $io->error('Expected --limit and --max-attempts to be positive and --older-than to be non-negative.');

            return Command::INVALID;
        }

        $createdBefore = new \DateTimeImmutable(sprintf('-%d seconds', $olderThan));

        return $this->send->handle($createdBefore, $limit, $maxAttempts)->run()->fold(
            function (Error $error) use ($io): int {
                $io->error(sprintf('%s (%s)', $error->message(), $error->code()));

                return Command::FAILURE;
            },
            function (SendReport $report) use ($io): int {
                $io->writeln(sprintf('Sent %d notification(s), %d failed.', $report->sent, $report->failed));

                return $report->failed > 0 ? Command::FAILURE : Command::SUCCESS;
            },
        );
    }

    private static function integerOption(InputInterface $input, string $name): int
    {
        $value = $input->getOption($name);

        return is_numeric($value) ? (int) $value : -1;
    }
}
