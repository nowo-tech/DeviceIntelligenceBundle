<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Signal\Normalizer;

use Nowo\DeviceIntelligence\Signal\Signal;
use Nowo\DeviceIntelligence\Signal\SignalName;

interface SignalNormalizerInterface
{
    public function supports(SignalName $name): bool;

    public function normalize(Signal $signal): Signal;
}
