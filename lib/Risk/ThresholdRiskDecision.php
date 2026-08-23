<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk;

final readonly class ThresholdRiskDecision implements RiskDecisionInterface
{
    public function __construct(
        public int $observe = 40,
        public int $stepUp = 70,
        public int $block = 90,
    ) {
    }

    public function decide(RiskAssessment $assessment): RiskDecision
    {
        $score = $assessment->score();
        $action = match (true) {
            $score >= $this->block => RiskDecisionAction::Block,
            $score >= $this->stepUp => RiskDecisionAction::StepUp,
            $score >= $this->observe => RiskDecisionAction::Observe,
            default => RiskDecisionAction::Allow,
        };

        return new RiskDecision($action);
    }
}
