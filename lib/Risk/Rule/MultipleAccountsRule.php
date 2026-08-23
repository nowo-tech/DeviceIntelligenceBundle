<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk\Rule;

use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Risk\RiskResult;
use Nowo\DeviceIntelligence\Risk\RiskRuleInterface;
use Nowo\DeviceIntelligence\Risk\RiskSeverity;

final class MultipleAccountsRule implements RiskRuleInterface
{
    public function __construct(private int $threshold = 3)
    {
    }

    public function name(): string
    {
        return 'multiple_accounts';
    }

    public function evaluate(RiskContext $context): RiskResult
    {
        $n = \count($context->userRelations);
        if ($n < $this->threshold) {
            return new RiskResult(0, $this->name(), ['accounts' => $n]);
        }
        $extra = $n - ($this->threshold - 1);
        $score = min(40, 15 * $extra);

        return new RiskResult($score, $this->name(), ['accounts' => $n], RiskSeverity::High);
    }
}
