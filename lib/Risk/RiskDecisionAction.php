<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk;

enum RiskDecisionAction: string
{
    case Allow = 'allow';
    case Observe = 'observe';
    case StepUp = 'step_up';
    case Block = 'block';
}
