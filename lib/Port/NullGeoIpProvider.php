<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Port;

final class NullGeoIpProvider implements GeoIpProviderInterface
{
    public function locate(string $ip): ?GeoIpResult
    {
        unset($ip);

        return null;
    }
}
