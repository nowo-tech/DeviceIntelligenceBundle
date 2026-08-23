<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk;

interface RiskDecisionInterface
{
    public function decide(RiskAssessment $assessment): RiskDecision;
}
