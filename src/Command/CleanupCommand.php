<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Command;

use DateInterval;
use DateTimeImmutable;
use Nowo\DeviceIntelligence\Port\ObservationRepositoryInterface;
use Nowo\DeviceIntelligenceBundle\Messenger\CleanupMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

use function sprintf;

use const DATE_ATOM;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsCommand(name: 'device-intelligence:cleanup', description: 'Delete observations older than a lookback interval')]
final class CleanupCommand extends Command
{
    public function __construct(
        private ObservationRepositoryInterface $observations,
        private ?MessageBusInterface $bus = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('older-than', null, InputOption::VALUE_REQUIRED, 'PHP DateInterval spec', 'P180D')
            ->addOption('async', null, InputOption::VALUE_NONE, 'Dispatch CleanupMessage when Messenger is enabled');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $spec = (string) $input->getOption('older-than');
        if ($input->getOption('async') && $this->bus !== null) {
            $this->bus->dispatch(new CleanupMessage($spec));
            $io->success('Cleanup dispatched.');

            return Command::SUCCESS;
        }

        $cutoff  = (new DateTimeImmutable())->sub(new DateInterval($spec));
        $deleted = $this->observations->deleteOlderThan($cutoff);
        $io->success(sprintf('Deleted %d observation(s) older than %s.', $deleted, $cutoff->format(DATE_ATOM)));

        return Command::SUCCESS;
    }
}
