<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Signal;

enum SignalSource: string
{
    case Client = 'client';
    case Server = 'server';
}
