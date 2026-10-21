<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Port;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Device\DeviceId;

interface DeviceRepositoryInterface
{
    public function find(DeviceId $id): ?Device;

    public function save(Device $device): void;

    /**
     * @return list<Device>
     */
    public function findCandidates(
        string $osFamily,
        string $browserFamily,
        ?string $timezone,
        ?string $gpuFamily,
        int $limit,
        DateTimeImmutable $since,
    ): array;
}
