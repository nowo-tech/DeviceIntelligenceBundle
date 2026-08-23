<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Matching\Comparator;

use Nowo\DeviceIntelligence\Signal\Signal;

interface SignalComparatorInterface
{
    public function similarity(?Signal $incoming, ?Signal $stored): float;
}
