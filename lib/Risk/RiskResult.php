<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk;

final readonly class RiskResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public int $scoreContribution,
        public string $reason,
        public array $metadata = [],
        public RiskSeverity $severity = RiskSeverity::Info,
        public bool $skipped = false,
    ) {
    }

    public static function skipped(string $reason, string $why = 'provider_unavailable'): self
    {
        return new self(0, $reason, ['skip' => $why], RiskSeverity::Info, true);
    }
}
