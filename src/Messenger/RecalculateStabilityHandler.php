<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Messenger;

use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Device\Stability;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Port\DeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Port\ObservationRepositoryInterface;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineDeviceRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Recalculates EMA-style stability from stored observations. Does not rematch.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsMessageHandler]
final class RecalculateStabilityHandler
{
    public function __construct(
        private DeviceRepositoryInterface $devices,
        private ObservationRepositoryInterface $observations,
    ) {
    }

    public function __invoke(RecalculateStabilityMessage $message): int
    {
        $targets = [];
        if (null !== $message->deviceId && '' !== $message->deviceId) {
            $device = $this->devices->find(new DeviceId($message->deviceId));
            if (null !== $device) {
                $targets[] = $device;
            }
        } elseif ($this->devices instanceof InMemoryDeviceRepository || $this->devices instanceof DoctrineDeviceRepository) {
            $targets = $this->devices->all();
        }

        $updated = 0;
        foreach ($targets as $device) {
            if ($this->recalculate($device)) {
                ++$updated;
            }
        }

        return $updated;
    }

    private function recalculate(Device $device): bool
    {
        $history = $this->observations->latestForDevice($device, 20);
        if (\count($history) < 2) {
            return false;
        }
        $newest = $history[0];
        $report = $device->compare($newest);
        $next = 0.88 * $device->stability() + 0.12 * (1 - $report->mutationScore());
        $updated = new Device(
            $device->id,
            $device->firstSeenAt,
            $device->lastSeenAt,
            $device->observationCount,
            $device->confidence,
            Stability::clamp($next),
            $device->status,
            $device->indexKey,
            $device->label,
            $device->metadata,
            $device->lastSignals,
        );
        $this->devices->save($updated);

        return true;
    }
}
