<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Messenger;

use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Device\Stability;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Port\AtomicDeviceRepositoryInterface;
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
    /**
     * Creates the recalculate stability handler.
     */
    public function __construct(
        private DeviceRepositoryInterface $devices,
        private ObservationRepositoryInterface $observations,
    ) {
    }

    /**
     * Recomputes the stability score of the device.
     */
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
        if ($this->devices instanceof AtomicDeviceRepositoryInterface) {
            // 1.10 (REQ-RUNTIME-003 rule 4): the stability alone, so a concurrent analysis keeps its count and last-seen time
            $this->devices->saveStability($device->id, Stability::clamp($next));

            return true;
        }
        // 1.10.1 (audit UE-DI-1): an adapter without the atomic port gets the device read again just before save(), so
        // only the stability changes and a status, count or last-seen time written meanwhile is kept; a device that is
        // gone is left gone. The re-read and save() are still two calls: only the atomic port closes the gap completely.
        $stored = $this->devices->find($device->id);
        if (null === $stored) {
            return false;
        }
        $updated = new Device(
            $stored->id,
            $stored->firstSeenAt,
            $stored->lastSeenAt,
            $stored->observationCount,
            $stored->confidence,
            Stability::clamp($next),
            $stored->status,
            $stored->indexKey,
            $stored->label,
            $stored->metadata,
            $stored->lastSignals,
        );
        $this->devices->save($updated);

        return true;
    }
}
