<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk;

interface RiskEngineInterface
{
    public function assess(RiskContext $context): RiskAssessment;
}
