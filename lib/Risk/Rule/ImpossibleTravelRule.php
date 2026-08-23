<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk\Rule;

use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Risk\RiskResult;
use Nowo\DeviceIntelligence\Risk\RiskRuleInterface;
use Nowo\DeviceIntelligence\Risk\RiskSeverity;

final class ImpossibleTravelRule implements RiskRuleInterface
{
    public function name(): string
    {
        return 'impossible_travel';
    }

    public function evaluate(RiskContext $context): RiskResult
    {
        if ($context->geo === null) {
            return RiskResult::skipped($this->name());
        }
        $previous = $context->previousCountry;
        $current  = $context->observation->country ?? $context->geo->country;
        if ($previous === null || $previous === $current) {
            return new RiskResult(0, $this->name());
        }
        $hours = max(0.01, ($context->observation->createdAt->getTimestamp() - $context->device->lastSeenAt->getTimestamp()) / 3600);
        if ($hours >= 6) {
            return new RiskResult(15, $this->name(), ['from' => $previous, 'to' => $current], RiskSeverity::Low);
        }

        return new RiskResult(45, $this->name(), ['from' => $previous, 'to' => $current, 'hours' => $hours], RiskSeverity::High);
    }
}
