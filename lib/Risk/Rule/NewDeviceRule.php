<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk\Rule;

use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Risk\RiskResult;
use Nowo\DeviceIntelligence\Risk\RiskRuleInterface;
use Nowo\DeviceIntelligence\Risk\RiskSeverity;

final class NewDeviceRule implements RiskRuleInterface
{
    public function name(): string
    {
        return 'new_device';
    }

    public function evaluate(RiskContext $context): RiskResult
    {
        if (!$context->match->isNewDevice()) {
            return new RiskResult(0, $this->name());
        }

        return new RiskResult(10, $this->name(), ['new' => true], RiskSeverity::Low);
    }
}
