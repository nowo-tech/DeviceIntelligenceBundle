<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Matching;

use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Observation\DeviceObservation;

interface DeviceMatcherInterface
{
    /**
     * @param iterable<Device> $candidates
     */
    public function match(DeviceObservation $observation, iterable $candidates): DeviceMatch;
}
