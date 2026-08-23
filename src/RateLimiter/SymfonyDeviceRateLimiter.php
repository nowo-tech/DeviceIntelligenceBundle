<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\RateLimiter;

use Nowo\DeviceIntelligenceBundle\Config\DeviceIntelligenceConfig;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Throwable;

use function count;
use function is_array;

/**
 * Uses Symfony RateLimiter factories when configured; otherwise in-memory/cache counting.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SymfonyDeviceRateLimiter implements DeviceRateLimiterInterface
{
    /** @var array<string, array<int>> */
    private array $memory = [];

    /**
     * @param array<string, RateLimiterFactory> $factories
     */
    public function __construct(
        private DeviceIntelligenceConfig $config,
        private CacheInterface $cache,
        private array $factories = [],
    ) {
    }

    public function consume(
        string $policy,
        string $key,
        ?string $ipHash,
        ?string $userId,
        ?string $deviceId,
        ?int $limit = null,
        ?string $interval = null,
    ): bool {
        $compound = $this->compoundKey($key, $ipHash, $userId, $deviceId);
        $factory  = $this->factories[$policy] ?? null;
        if ($factory instanceof RateLimiterFactory) {
            return $factory->create($compound)->consume()->isAccepted();
        }

        $profile  = $this->config->profile();
        $policies = $profile['rate_limit']['policies'] ?? [];
        $cfg      = is_array($policies[$policy] ?? null) ? $policies[$policy] : [];
        $max      = $limit ?? (int) ($cfg['limit'] ?? 60);
        $window   = $interval ?? (string) ($cfg['interval'] ?? '1 minute');
        $seconds  = $this->intervalSeconds($window);
        $bucket   = 'di.rl.' . $policy . '.' . $compound;
        $now      = time();

        try {
            $hits = $this->cache->get($bucket, []);
        } catch (Throwable) {
            $hits = $this->memory[$bucket] ?? [];
        }
        if (!is_array($hits)) {
            $hits = [];
        }
        $cutoff = $now - $seconds;
        $hits   = array_values(array_filter($hits, static fn (mixed $ts): bool => (int) $ts >= $cutoff));
        if (count($hits) >= $max) {
            return false;
        }
        $hits[] = $now;
        try {
            $this->cache->set($bucket, $hits, $seconds);
        } catch (Throwable) {
            $this->memory[$bucket] = $hits;
        }

        return true;
    }

    private function compoundKey(string $key, ?string $ipHash, ?string $userId, ?string $deviceId): string
    {
        $ip     = $ipHash ?? 'anon';
        $user   = $userId ?? 'anon';
        $device = $deviceId ?? 'anon';

        return match ($key) {
            'user'        => 'u:' . $user,
            'device'      => 'd:' . $device,
            'device_ip'   => 'di:' . $device . ':' . $ip,
            'user_device' => 'ud:' . $user . ':' . $device,
            default       => 'ip:' . $ip,
        };
    }

    private function intervalSeconds(string $interval): int
    {
        if (preg_match('/^(\d+)\s+seconds?$/i', $interval, $m)) {
            return max(1, (int) $m[1]);
        }
        if (preg_match('/^(\d+)\s+minutes?$/i', $interval, $m)) {
            return max(1, (int) $m[1] * 60);
        }
        if (preg_match('/^(\d+)\s+hours?$/i', $interval, $m)) {
            return max(1, (int) $m[1] * 3600);
        }
        if (preg_match('/^PT(\d+)S$/i', $interval, $m)) {
            return max(1, (int) $m[1]);
        }
        if (preg_match('/^PT(\d+)M$/i', $interval, $m)) {
            return max(1, (int) $m[1] * 60);
        }

        return 60;
    }
}
