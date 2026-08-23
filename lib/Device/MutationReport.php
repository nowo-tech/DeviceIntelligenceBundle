<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Device;

use Nowo\DeviceIntelligence\Signal\SignalName;

final readonly class MutationReport
{
    /**
     * @param list<SignalName> $changedSignals
     * @param list<SignalName> $stableSignals
     */
    public function __construct(
        public array $changedSignals,
        public array $stableSignals,
        public float $mutationScore,
    ) {
    }

    /**
     * @return list<SignalName>
     */
    public function changedSignals(): array
    {
        return $this->changedSignals;
    }

    public function mutationScore(): float
    {
        return $this->mutationScore;
    }
}
