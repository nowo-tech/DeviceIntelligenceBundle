<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk\Rule;

use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Risk\RiskResult;
use Nowo\DeviceIntelligence\Risk\RiskRuleInterface;
use Nowo\DeviceIntelligence\Risk\RiskSeverity;

final class TrustedDeviceRule implements RiskRuleInterface
{
    public function name(): string
    {
        return 'trusted_device';
    }

    public function evaluate(RiskContext $context): RiskResult
    {
        if (!$context->trusted) {
            return new RiskResult(0, $this->name());
        }

        return new RiskResult(-25, $this->name(), [], RiskSeverity::Info);
    }
}
