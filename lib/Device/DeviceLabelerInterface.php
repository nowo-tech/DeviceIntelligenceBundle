<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Device;

use Nowo\DeviceIntelligence\Signal\SignalBag;

interface DeviceLabelerInterface
{
    public function label(SignalBag $signals): string;
}
