<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Matching\Candidate;

use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Observation\DeviceObservation;

interface DeviceCandidateProviderInterface
{
    /**
     * @return iterable<Device>
     */
    public function candidates(DeviceObservation $observation): iterable;
}
