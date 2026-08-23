<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence;

use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Matching\DeviceMatch;
use Nowo\DeviceIntelligence\Observation\DeviceObservation;
use Nowo\DeviceIntelligence\Risk\RiskAssessment;
use Nowo\DeviceIntelligence\Signal\SignalBag;

final readonly class Analysis
{
    /**
     * @param array<string, float> $timings milliseconds per phase
     */
    public function __construct(
        public Device $device,
        public DeviceMatch $match,
        public RiskAssessment $risk,
        public DeviceObservation $observation,
        public SignalBag $signals,
        public bool $degraded = false,
        public array $timings = [],
    ) {
    }

    public function device(): Device
    {
        return $this->device;
    }

    public function match(): DeviceMatch
    {
        return $this->match;
    }

    public function risk(): RiskAssessment
    {
        return $this->risk;
    }

    public function observation(): DeviceObservation
    {
        return $this->observation;
    }

    public function signals(): SignalBag
    {
        return $this->signals;
    }

    public function matchConfidence(): float
    {
        return $this->match->confidence();
    }

    public function riskScore(): int
    {
        return $this->risk->score();
    }

    public function riskLevel(): string
    {
        return $this->risk->level()->value;
    }

    /**
     * @return list<string>
     */
    public function riskReasons(): array
    {
        return $this->risk->reasons();
    }

    public function degraded(): bool
    {
        return $this->degraded;
    }
}
