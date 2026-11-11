<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Support\Host;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Observation\DeviceObservation;
use Nowo\DeviceIntelligence\Observation\ObservationId;
use Nowo\DeviceIntelligence\Port\ObservationRepositoryInterface;

/**
 * A host's own observation store (kept across requests; keeps the latest 10 per device, like a retention job).
 */
final class HostObservationStore implements ObservationRepositoryInterface
{
    public int $writes = 0;

    /** @var array<string, list<DeviceObservation>> device id => newest first */
    private array $rows = [];

    public function save(DeviceObservation $observation): void
    {
        ++$this->writes;
        $rows = $this->rows[$observation->deviceId->value] ?? [];
        array_unshift($rows, $observation);
        $this->rows[$observation->deviceId->value] = \array_slice($rows, 0, 10);
    }

    public function find(ObservationId $id): ?DeviceObservation
    {
        foreach ($this->rows as $rows) {
            foreach ($rows as $row) {
                if ($row->id->value === $id->value) {
                    return $row;
                }
            }
        }

        return null;
    }

    public function latestForDevice(Device $device, int $limit = 10): array
    {
        return \array_slice($this->rows[$device->id->value] ?? [], 0, $limit);
    }

    public function deleteOlderThan(DateTimeImmutable $cutoff): int
    {
        return 0;
    }
}
