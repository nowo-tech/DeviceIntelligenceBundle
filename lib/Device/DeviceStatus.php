<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Device;

enum DeviceStatus: string
{
    case Active  = 'active';
    case Dormant = 'dormant';
    case Merged  = 'merged';
    case Revoked = 'revoked';
}
