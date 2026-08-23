<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Signal\Server;

use Nowo\DeviceIntelligence\AnalysisInput;
use Nowo\DeviceIntelligence\Signal\Signal;

interface ServerSignalProviderInterface
{
    /**
     * @return iterable<Signal>
     */
    public function collect(AnalysisInput $input): iterable;
}
