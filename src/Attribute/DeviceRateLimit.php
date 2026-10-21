<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Attribute;

use Attribute;

/**
 * Rate-limit a controller using hashed IP and/or device ULID keys.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class DeviceRateLimit
{
    /**
     * @param 'device'|'device_ip'|'ip'|'user'|'user_device' $policy
     */
    public function __construct(
        public int $limit = 60,
        public string $interval = '1 minute',
        public string $policy = 'ip',
    ) {
    }
}
