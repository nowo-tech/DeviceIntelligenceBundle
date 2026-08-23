<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk;

enum RiskLevel: string
{
    case Low      = 'low';
    case Medium   = 'medium';
    case High     = 'high';
    case Critical = 'critical';

    public function isHigh(): bool
    {
        return $this === self::High || $this === self::Critical;
    }
}
