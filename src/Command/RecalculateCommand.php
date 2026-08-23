<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Command;

use Nowo\DeviceIntelligenceBundle\Messenger\RecalculateStabilityHandler;
use Nowo\DeviceIntelligenceBundle\Messenger\RecalculateStabilityMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

use function is_string;
use function sprintf;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsCommand(name: 'device-intelligence:recalculate', description: 'Recalculate device stability from stored observations')]
final class RecalculateCommand extends Command
{
    public function __construct(
        private RecalculateStabilityHandler $handler,
        private ?MessageBusInterface $bus = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('deviceId', InputArgument::OPTIONAL, 'Device ULID (omit for all when using Doctrine)')
            ->addOption('async', null, InputOption::VALUE_NONE, 'Dispatch RecalculateStabilityMessage');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $id      = $input->getArgument('deviceId');
        $id      = is_string($id) && $id !== '' ? $id : null;
        $message = new RecalculateStabilityMessage($id);
        if ($input->getOption('async') && $this->bus !== null) {
            $this->bus->dispatch($message);
            $io->success('Recalculation dispatched.');

            return Command::SUCCESS;
        }

        $updated = ($this->handler)($message);
        $io->success(sprintf('Updated stability on %d device(s).', $updated));

        return Command::SUCCESS;
    }
}
