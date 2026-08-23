<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk\Rule;

use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Risk\RiskResult;
use Nowo\DeviceIntelligence\Risk\RiskRuleInterface;
use Nowo\DeviceIntelligence\Risk\RiskSeverity;

final class SuspiciousLoginRule implements RiskRuleInterface
{
    public function name(): string
    {
        return 'suspicious_login';
    }

    public function evaluate(RiskContext $context): RiskResult
    {
        $failures = $context->velocity['login_failure'] ?? 0;
        if ($failures < 5) {
            return new RiskResult(0, $this->name(), ['failures' => $failures]);
        }

        return new RiskResult(min(20, $failures * 3), $this->name(), ['failures' => $failures], RiskSeverity::Medium);
    }
}
