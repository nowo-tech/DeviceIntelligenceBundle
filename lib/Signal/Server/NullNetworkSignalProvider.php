<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Signal\Server;

use Nowo\DeviceIntelligence\AnalysisInput;

final class NullNetworkSignalProvider implements NetworkSignalProviderInterface
{
    public function collect(AnalysisInput $input): iterable
    {
        unset($input);

        return [];
    }
}
