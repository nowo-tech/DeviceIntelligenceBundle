<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Port;

use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Device\Stability;

/**
 * Optional, for a {@see DeviceRepositoryInterface} adapter that can write a device without a read-modify-write of the
 * whole row (REQ-RUNTIME-003 rule 4, 1.10). When the adapter implements it, the analysis and the stability recalculation
 * use it. Otherwise they read the device again with find() just before save() and keep its stored status (1.10.1), which
 * narrows but does not close the window in which a concurrent status change is reverted. An adapter whose storage is
 * shared between requests or workers must implement this port, each method as one atomic write, to be fully protected.
 */
interface AtomicDeviceRepositoryInterface extends DeviceRepositoryInterface
{
    /**
     * Stores a device that has just gained one observation: the stored observation count is incremented in storage
     * (never overwritten with a count read earlier), the observation fields (last seen, confidence, stability, index
     * key, label, last signals) are written, and the status, first-seen time and metadata are left as they are, so a
     * concurrent status change is never reverted. A device that is not stored yet is inserted as it is.
     */
    public function saveObserved(Device $device): void;

    /**
     * Writes only the stability score of a stored device; a device that is gone is left gone.
     */
    public function saveStability(DeviceId $id, Stability $stability): void;
}
