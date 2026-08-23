<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Command;

use Nowo\DeviceIntelligence\Device\DeviceManager;
use Nowo\DeviceIntelligence\User\UserIdentifier;
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
#[AsCommand(name: 'device-intelligence:user:devices', description: 'List devices associated with a user identifier')]
final class UserDevicesCommand extends Command
{
    public function __construct(private DeviceManager $devices)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('user', InputArgument::REQUIRED, 'User identifier');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = [];
        foreach ($this->devices->devicesForUser(new UserIdentifier((string) $input->getArgument('user'))) as $device) {
            $rows[] = [
                $device->id->value,
                $device->label,
                $device->status->value,
                (string) $device->confidence->value,
                $device->lastSeenAt->format(\DATE_ATOM),
            ];
        }
        if ([] === $rows) {
            $io->warning('No devices for this user.');

            return Command::SUCCESS;
        }
        $io->table(['Device', 'Label', 'Status', 'Confidence', 'Last seen'], $rows);

        return Command::SUCCESS;
    }
}
