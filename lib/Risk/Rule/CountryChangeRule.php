<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk\Rule;

use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Risk\RiskResult;
use Nowo\DeviceIntelligence\Risk\RiskRuleInterface;
use Nowo\DeviceIntelligence\Risk\RiskSeverity;

final class CountryChangeRule implements RiskRuleInterface
{
    public function name(): string
    {
        return 'country_change';
    }

    public function evaluate(RiskContext $context): RiskResult
    {
        $current  = $context->observation->country;
        $previous = $context->previousCountry;
        if ($current === null || $previous === null || $current === $previous) {
            return new RiskResult(0, $this->name());
        }

        return new RiskResult(15, $this->name(), ['from' => $previous, 'to' => $current], RiskSeverity::Medium);
    }
}
