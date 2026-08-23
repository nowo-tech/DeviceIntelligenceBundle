<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Observation;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Privacy\IpHash;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligence\User\UserIdentifier;

/**
 * Immutable visit snapshot. deviceId is assigned before persist.
 */
final readonly class DeviceObservation
{
    public function __construct(
        public ObservationId $id,
        public DeviceId $deviceId,
        public DateTimeImmutable $createdAt,
        public int $schemaVersion,
        public ?string $sdkVersion,
        public ?IpHash $ipHash,
        public ?string $country,
        public ?string $userAgentFamily,
        public ?string $rawUserAgent,
        public ?string $sessionIdentifier,
        public ?UserIdentifier $userIdentifier,
        public SignalBag $signals,
        public int $riskScore,
        public bool $degraded,
        public int $enhancementLevel,
    ) {
    }

    public function withRiskScore(int $score): self
    {
        return new self(
            $this->id,
            $this->deviceId,
            $this->createdAt,
            $this->schemaVersion,
            $this->sdkVersion,
            $this->ipHash,
            $this->country,
            $this->userAgentFamily,
            $this->rawUserAgent,
            $this->sessionIdentifier,
            $this->userIdentifier,
            $this->signals,
            $score,
            $this->degraded,
            $this->enhancementLevel,
        );
    }
}
