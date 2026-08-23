<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Velocity;

use Nowo\DeviceIntelligence\Device\Device;

interface VelocityEngineInterface
{
    public function increment(string $key, Device $device, int $by = 1): void;

    public function count(string $key, Device $device, TimeWindow $window): int;
}
