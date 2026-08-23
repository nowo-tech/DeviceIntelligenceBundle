<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk\Rule;

use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Risk\RiskResult;
use Nowo\DeviceIntelligence\Risk\RiskRuleInterface;
use Nowo\DeviceIntelligence\Risk\RiskSeverity;

final class IpChangeRule implements RiskRuleInterface
{
    public function name(): string
    {
        return 'ip_change';
    }

    public function evaluate(RiskContext $context): RiskResult
    {
        $current  = $context->observation->ipHash?->value;
        $previous = $context->previousIpHash;
        if ($current === null || $previous === null || $current === $previous) {
            return new RiskResult(0, $this->name());
        }

        return new RiskResult(8, $this->name(), [], RiskSeverity::Low);
    }
}
