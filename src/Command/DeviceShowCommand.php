<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Command;

use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Device\DeviceManager;
use Nowo\DeviceIntelligence\Port\ObservationRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsCommand(name: 'device-intelligence:device:show', description: 'Show a stored device by ULID')]
final class DeviceShowCommand extends Command
{
    public function __construct(
        private DeviceManager $devices,
        private ObservationRepositoryInterface $observations,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('deviceId', InputArgument::REQUIRED, 'Device ULID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $device = $this->devices->get(new DeviceId((string) $input->getArgument('deviceId')));
        if (null === $device) {
            $io->error('Device not found.');

            return Command::FAILURE;
        }

        $io->definitionList(
            ['ID' => $device->id->value],
            ['Label' => $device->label],
            ['Status' => $device->status->value],
            ['Confidence' => (string) $device->confidence->value],
            ['Stability' => (string) $device->stability()],
            ['Observations' => (string) $device->observationCount],
            ['First seen' => $device->firstSeenAt->format(\DATE_ATOM)],
            ['Last seen' => $device->lastSeenAt->format(\DATE_ATOM)],
            ['OS' => $device->indexKey->osFamily],
            ['Browser' => $device->indexKey->browserFamily],
        );

        $latest = $this->observations->latestForDevice($device, 5);
        $io->section('Recent observations (ids only)');
        foreach ($latest as $obs) {
            $io->writeln(\sprintf('%s  risk=%d  %s', $obs->id->value, $obs->riskScore, $obs->createdAt->format(\DATE_ATOM)));
        }

        return Command::SUCCESS;
    }
}
