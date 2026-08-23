<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk\Rule;

use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Risk\RiskResult;
use Nowo\DeviceIntelligence\Risk\RiskRuleInterface;
use Nowo\DeviceIntelligence\Risk\RiskSeverity;

final class RapidAccountCreationRule implements RiskRuleInterface
{
    public function name(): string
    {
        return 'rapid_account_creation';
    }

    public function evaluate(RiskContext $context): RiskResult
    {
        $n = $context->velocity['registration'] ?? 0;
        if ($n < 3) {
            return new RiskResult(0, $this->name(), ['registrations' => $n]);
        }

        return new RiskResult(min(25, 8 * $n), $this->name(), ['registrations' => $n], RiskSeverity::Medium);
    }
}
