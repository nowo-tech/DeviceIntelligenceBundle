<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Port;

use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Observation\DeviceObservation;
use Nowo\DeviceIntelligence\Observation\ObservationId;

interface ObservationRepositoryInterface
{
    public function save(DeviceObservation $observation): void;

    public function find(ObservationId $id): ?DeviceObservation;

    /**
     * @return list<DeviceObservation>
     */
    public function latestForDevice(Device $device, int $limit = 10): array;

    public function deleteOlderThan(\DateTimeImmutable $cutoff): int;
}
