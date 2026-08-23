<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk;

interface RiskRuleInterface
{
    public function name(): string;

    public function evaluate(RiskContext $context): RiskResult;
}
