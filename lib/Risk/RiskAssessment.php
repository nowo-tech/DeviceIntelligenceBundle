<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk;

final readonly class RiskAssessment
{
    /**
     * @param list<RiskReason> $reasons
     */
    public function __construct(
        public RiskScore $score,
        public RiskLevel $level,
        public array $reasons,
    ) {
    }

    public function score(): int
    {
        return $this->score->value;
    }

    public function level(): RiskLevel
    {
        return $this->level;
    }

    public function isHigh(): bool
    {
        return $this->level->isHigh();
    }

    /**
     * @return list<string>
     */
    public function reasons(): array
    {
        $out = [];
        foreach ($this->reasons as $reason) {
            $out[] = $reason->label();
        }

        return $out;
    }
}
