<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Port;

final readonly class IpReputation
{
    public function __construct(
        public bool $vpn = false,
        public bool $proxy = false,
        public bool $tor = false,
        public bool $hosting = false,
        public bool $residential = true,
        public int $risk = 0,
    ) {
    }
}
