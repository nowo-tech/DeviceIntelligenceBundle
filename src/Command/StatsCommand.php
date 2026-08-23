<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Command;

use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Port\DeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Port\DeviceUserRepositoryInterface;
use Nowo\DeviceIntelligence\Port\ObservationRepositoryInterface;
use Nowo\DeviceIntelligence\Port\TrustedDeviceRepositoryInterface;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineDeviceRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineDeviceUserRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineObservationRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineTrustedDeviceRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsCommand(name: 'device-intelligence:stats', description: 'Show stored device / observation / relation counts')]
final class StatsCommand extends Command
{
    public function __construct(
        private DeviceRepositoryInterface $devices,
        private ObservationRepositoryInterface $observations,
        private DeviceUserRepositoryInterface $users,
        private TrustedDeviceRepositoryInterface $trusts,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->definitionList(
            ['Devices' => (string) $this->countDevices()],
            ['Observations' => (string) $this->countObservations()],
            ['Device-user rows' => (string) $this->countUsers()],
            ['Trust rows' => (string) $this->countTrusts()],
        );

        return Command::SUCCESS;
    }

    private function countDevices(): int
    {
        if ($this->devices instanceof DoctrineDeviceRepository) {
            return $this->devices->countAll();
        }
        if ($this->devices instanceof InMemoryDeviceRepository) {
            return count($this->devices->all());
        }

        return 0;
    }

    private function countObservations(): int
    {
        if ($this->observations instanceof DoctrineObservationRepository) {
            return $this->observations->countAll();
        }

        return 0;
    }

    private function countUsers(): int
    {
        if ($this->users instanceof DoctrineDeviceUserRepository) {
            return $this->users->countAll();
        }

        return 0;
    }

    private function countTrusts(): int
    {
        if ($this->trusts instanceof DoctrineTrustedDeviceRepository) {
            return $this->trusts->countAll();
        }

        return 0;
    }
}
