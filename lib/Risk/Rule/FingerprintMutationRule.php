<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk\Rule;

use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Risk\RiskResult;
use Nowo\DeviceIntelligence\Risk\RiskRuleInterface;
use Nowo\DeviceIntelligence\Risk\RiskSeverity;

final class FingerprintMutationRule implements RiskRuleInterface
{
    public function name(): string
    {
        return 'fingerprint_mutation';
    }

    public function evaluate(RiskContext $context): RiskResult
    {
        if ($context->match->isNewDevice()) {
            return new RiskResult(0, $this->name());
        }
        $report = $context->device->compare($context->observation);
        if ($report->mutationScore < 0.6) {
            return new RiskResult(0, $this->name(), ['mutation' => $report->mutationScore]);
        }

        return new RiskResult(35, $this->name(), ['mutation' => $report->mutationScore], RiskSeverity::High);
    }
}
