<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk;

use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Matching\DeviceMatch;
use Nowo\DeviceIntelligence\Observation\DeviceObservation;
use Nowo\DeviceIntelligence\Port\GeoIpResult;
use Nowo\DeviceIntelligence\Port\IpReputation;
use Nowo\DeviceIntelligence\User\DeviceUserRelation;

final readonly class RiskContext
{
    /**
     * @param list<DeviceUserRelation> $userRelations
     * @param array<string, int> $velocity
     */
    public function __construct(
        public DeviceObservation $observation,
        public Device $device,
        public DeviceMatch $match,
        public array $userRelations = [],
        public array $velocity = [],
        public bool $trusted = false,
        public ?GeoIpResult $geo = null,
        public ?IpReputation $reputation = null,
        public ?string $previousCountry = null,
        public ?string $previousIpHash = null,
        public ?string $previousSession = null,
    ) {
    }
}
