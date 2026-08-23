<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk\Rule;

use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Risk\RiskResult;
use Nowo\DeviceIntelligence\Risk\RiskRuleInterface;
use Nowo\DeviceIntelligence\Risk\RiskSeverity;

final class SessionChangeRule implements RiskRuleInterface
{
    public function name(): string
    {
        return 'session_change';
    }

    public function evaluate(RiskContext $context): RiskResult
    {
        $current = $context->observation->sessionIdentifier;
        $previous = $context->previousSession;
        if (null === $current || null === $previous || $current === $previous) {
            return new RiskResult(0, $this->name());
        }

        return new RiskResult(10, $this->name(), [], RiskSeverity::Low);
    }
}
