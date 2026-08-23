<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Port;

final readonly class GeoIpResult
{
    public function __construct(
        public string $country,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $asn = null,
    ) {
    }
}
