<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk\Rule;

use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Risk\RiskResult;
use Nowo\DeviceIntelligence\Risk\RiskRuleInterface;
use Nowo\DeviceIntelligence\Risk\RiskSeverity;
use Nowo\DeviceIntelligence\Signal\SignalName;

final class AutomationRule implements RiskRuleInterface
{
    public function name(): string
    {
        return 'automation';
    }

    public function evaluate(RiskContext $context): RiskResult
    {
        $signal = $context->observation->signals->get(SignalName::AutomationIndicators);
        if (null === $signal) {
            return new RiskResult(0, $this->name());
        }
        $value = $signal->normalizedValue;
        $confidence = 0.0;
        $indicators = [];
        if (\is_array($value)) {
            $confidence = (float) ($value['confidence'] ?? 0);
            $indicators = \is_array($value['indicators'] ?? null) ? $value['indicators'] : [];
        }
        if ($confidence < 0.4 && [] === $indicators) {
            return new RiskResult(0, $this->name());
        }
        $score = (int) round(50 * min(1.0, max($confidence, [] !== $indicators ? 0.6 : 0.0)));

        return new RiskResult($score, $this->name(), [
            'confidence' => $confidence,
            'indicators' => $indicators,
        ], $score >= 40 ? RiskSeverity::High : RiskSeverity::Medium);
    }
}
