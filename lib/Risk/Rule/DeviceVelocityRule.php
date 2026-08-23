<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk\Rule;

use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Risk\RiskResult;
use Nowo\DeviceIntelligence\Risk\RiskRuleInterface;
use Nowo\DeviceIntelligence\Risk\RiskSeverity;

final class DeviceVelocityRule implements RiskRuleInterface
{
    public function name(): string
    {
        return 'device_velocity';
    }

    public function evaluate(RiskContext $context): RiskResult
    {
        $requests = $context->velocity['request'] ?? 0;
        if ($requests < 120) {
            return new RiskResult(0, $this->name(), ['requests' => $requests]);
        }

        return new RiskResult(min(15, (int) floor($requests / 40)), $this->name(), ['requests' => $requests], RiskSeverity::Medium);
    }
}
