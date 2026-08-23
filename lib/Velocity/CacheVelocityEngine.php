<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Velocity;

use Nowo\DeviceIntelligence\Device\Device;
use Psr\SimpleCache\CacheInterface;

final class CacheVelocityEngine implements VelocityEngineInterface
{
    public function __construct(
        private CacheInterface $cache,
        private string $prefix = 'di.vel.',
    ) {
    }

    public function increment(string $key, Device $device, int $by = 1): void
    {
        $cacheKey = $this->prefix.$key.'.'.$device->id->value;
        $payload = $this->cache->get($cacheKey, []);
        if (!\is_array($payload)) {
            $payload = [];
        }
        $now = time();
        for ($i = 0; $i < max(1, $by); ++$i) {
            $payload[] = $now;
        }
        $this->cache->set($cacheKey, $payload, 86400 * 7);
    }

    public function count(string $key, Device $device, TimeWindow $window): int
    {
        $cacheKey = $this->prefix.$key.'.'.$device->id->value;
        $payload = $this->cache->get($cacheKey, []);
        if (!\is_array($payload)) {
            return 0;
        }
        $cutoff = time() - $window->seconds();
        $n = 0;
        foreach ($payload as $ts) {
            if ((int) $ts >= $cutoff) {
                ++$n;
            }
        }

        return $n;
    }
}
