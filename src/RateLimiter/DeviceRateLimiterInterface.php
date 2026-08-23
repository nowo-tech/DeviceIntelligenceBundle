<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\RateLimiter;

/**
 * Rate limits keyed by hashed IP and/or device ULID. Never uses raw IP as a key.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface DeviceRateLimiterInterface
{
    /**
     * @param 'device'|'device_ip'|'ip'|'user'|'user_device' $key
     *
     * @return bool;
     */
    public function consume(
        string $policy,
        string $key,
        ?string $ipHash,
        ?string $userId,
        ?string $deviceId,
        ?int $limit = null,
        ?string $interval = null,
    ): bool;
}
