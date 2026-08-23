<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Matching;

use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Signal\SignalName;

final readonly class DeviceMatch
{
    /**
     * @param list<SignalName> $changedSignals
     * @param list<SignalName> $stableSignals
     */
    public function __construct(
        private ?Device $matchedDevice,
        private Confidence $matchConfidence,
        private Similarity $matchSimilarity,
        private array $changedSignals,
        private array $stableSignals,
        private bool $newDevice,
    ) {
    }

    public function device(): ?Device
    {
        return $this->matchedDevice;
    }

    public function confidence(): float
    {
        return $this->matchConfidence->value;
    }

    public function similarity(): float
    {
        return $this->matchSimilarity->value;
    }

    /**
     * @return list<SignalName>
     */
    public function changedSignals(): array
    {
        return $this->changedSignals;
    }

    /**
     * @return list<SignalName>
     */
    public function stableSignals(): array
    {
        return $this->stableSignals;
    }

    public function isNewDevice(): bool
    {
        return $this->newDevice;
    }
}
