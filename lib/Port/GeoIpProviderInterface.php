<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Port;

interface GeoIpProviderInterface
{
    public function locate(string $ip): ?GeoIpResult;
}
