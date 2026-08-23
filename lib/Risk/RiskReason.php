<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk;

final readonly class RiskReason
{
    public function __construct(
        public string $code,
        public int $contribution,
        public RiskSeverity $severity,
    ) {
    }

    public function label(): string
    {
        $sign = $this->contribution >= 0 ? '+' : '';

        return \sprintf('%s %s%d', $this->code, $sign, $this->contribution);
    }
}
