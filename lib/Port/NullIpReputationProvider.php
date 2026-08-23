<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Port;

final class NullIpReputationProvider implements IpReputationProviderInterface
{
    public function inspect(string $ip): ?IpReputation
    {
        unset($ip);

        return null;
    }
}
