<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Port;

interface IpReputationProviderInterface
{
    public function inspect(string $ip): ?IpReputation;
}
