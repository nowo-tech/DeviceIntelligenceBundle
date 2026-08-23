<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Infrastructure;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Observation\DeviceObservation;
use Nowo\DeviceIntelligence\Observation\ObservationId;
use Nowo\DeviceIntelligence\Port\ObservationRepositoryInterface;

use function array_slice;

final class InMemoryObservationRepository implements ObservationRepositoryInterface
{
    /** @var array<string, DeviceObservation> */
    private array $rows = [];

    public function save(DeviceObservation $observation): void
    {
        $this->rows[$observation->id->value] = $observation;
    }

    public function find(ObservationId $id): ?DeviceObservation
    {
        return $this->rows[$id->value] ?? null;
    }

    public function latestForDevice(Device $device, int $limit = 10): array
    {
        $out = [];
        foreach ($this->rows as $row) {
            if ($row->deviceId->equals($device->id)) {
                $out[] = $row;
            }
        }
        usort($out, static fn (DeviceObservation $a, DeviceObservation $b): int => $b->createdAt <=> $a->createdAt);

        return array_slice($out, 0, $limit);
    }

    public function deleteOlderThan(DateTimeImmutable $cutoff): int
    {
        $n = 0;
        foreach ($this->rows as $id => $row) {
            if ($row->createdAt < $cutoff) {
                unset($this->rows[$id]);
                ++$n;
            }
        }

        return $n;
    }
}
