<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk;

final readonly class RiskDecision
{
    public function __construct(public RiskDecisionAction $action)
    {
    }
}
