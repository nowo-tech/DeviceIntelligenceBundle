<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk;

enum RiskSeverity: string
{
    case Info   = 'info';
    case Low    = 'low';
    case Medium = 'medium';
    case High   = 'high';
}
