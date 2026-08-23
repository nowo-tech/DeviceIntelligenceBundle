<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk;

final readonly class RiskLevels
{
    public function __construct(
        public int $medium = 30,
        public int $high = 65,
        public int $critical = 90,
    ) {
    }

    public function levelFor(int $score): RiskLevel
    {
        return match (true) {
            $score >= $this->critical => RiskLevel::Critical,
            $score >= $this->high     => RiskLevel::High,
            $score >= $this->medium   => RiskLevel::Medium,
            default                   => RiskLevel::Low,
        };
    }
}
