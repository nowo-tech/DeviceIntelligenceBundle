<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Signal;

enum EntropyCategory: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
