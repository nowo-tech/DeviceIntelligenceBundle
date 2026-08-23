<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Infrastructure;

use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Device\DeviceStatus;
use Nowo\DeviceIntelligence\Port\DeviceRepositoryInterface;

final class InMemoryDeviceRepository implements DeviceRepositoryInterface
{
    /** @var array<string, Device> */
    private array $devices = [];

    public function find(DeviceId $id): ?Device
    {
        return $this->devices[$id->value] ?? null;
    }

    public function save(Device $device): void
    {
        $this->devices[$device->id->value] = $device;
    }

    public function findCandidates(
        string $osFamily,
        string $browserFamily,
        ?string $timezone,
        ?string $gpuFamily,
        int $limit,
        \DateTimeImmutable $since,
    ): array {
        $out = [];
        foreach ($this->devices as $device) {
            if (DeviceStatus::Active !== $device->status) {
                continue;
            }
            if ($device->lastSeenAt < $since) {
                continue;
            }
            if ($device->indexKey->osFamily !== $osFamily) {
                continue;
            }
            if ($device->indexKey->browserFamily !== $browserFamily) {
                continue;
            }
            if (null !== $timezone && $device->indexKey->timezone !== $timezone) {
                continue;
            }
            if (null !== $gpuFamily && $device->indexKey->gpuFamily !== $gpuFamily) {
                continue;
            }
            $out[] = $device;
            if (\count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return list<Device>
     */
    public function all(): array
    {
        return array_values($this->devices);
    }
}
