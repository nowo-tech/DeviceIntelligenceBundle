<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Support\Host;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Port\DeviceRepositoryInterface;

/**
 * A host's own device store (stands for its database: kept across requests, never reset by the bundle).
 */
final class HostDeviceStore implements DeviceRepositoryInterface
{
    public int $writes = 0;

    private readonly InMemoryDeviceRepository $rows;

    public function __construct()
    {
        $this->rows = new InMemoryDeviceRepository();
    }

    public function find(DeviceId $id): ?Device
    {
        return $this->rows->find($id);
    }

    public function save(Device $device): void
    {
        ++$this->writes;
        $this->rows->save($device);
    }

    public function findCandidates(string $osFamily, string $browserFamily, ?string $timezone, ?string $gpuFamily, int $limit, DateTimeImmutable $since): array
    {
        return $this->rows->findCandidates($osFamily, $browserFamily, $timezone, $gpuFamily, $limit, $since);
    }

    /**
     * @return list<Device>
     */
    public function all(): array
    {
        return $this->rows->all();
    }
}
