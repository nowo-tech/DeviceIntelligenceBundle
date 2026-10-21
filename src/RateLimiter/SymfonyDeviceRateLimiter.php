<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\RateLimiter;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use Nowo\DeviceIntelligenceBundle\Config\DeviceIntelligenceConfig;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

/**
 * Uses Symfony RateLimiter factories when configured; otherwise cache counting.
 *
 * When the cache pool fails, counters fall back to a request-scoped in-memory map (cleared by {@see reset()}).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SymfonyDeviceRateLimiter implements DeviceRateLimiterInterface, ResetInterface
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

    /**
     * Consumes one token of the device rate limiter and returns whether the call is accepted.
     */
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
        $factory = $this->factories[$policy] ?? null;
        if ($factory instanceof RateLimiterFactory) {
            return $factory->create($compound)->consume()->isAccepted();
        }

        $profile = $this->config->profile();
        $policies = $profile['rate_limit']['policies'] ?? [];
        $cfg = \is_array($policies[$policy] ?? null) ? $policies[$policy] : [];
        $max = $limit ?? (int) ($cfg['limit'] ?? 60);
        $window = $interval ?? (string) ($cfg['interval'] ?? '1 minute');
        $seconds = self::intervalToSeconds($window);
        $bucket = $this->cacheKey($policy, $compound);
        $now = time();

        try {
            $hits = $this->cache->get($bucket, []);
        } catch (Throwable) {
            $hits = $this->memory[$bucket] ?? [];
        }
        if (!\is_array($hits)) {
            $hits = [];
        }
        $cutoff = $now - $seconds;
        $hits = array_values(array_filter($hits, static fn (mixed $ts): bool => (int) $ts >= $cutoff));
        if (\count($hits) >= $max) {
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

    /**
     * Clears the in-memory limiter state.
     */
    public function reset(): void
    {
        $this->memory = [];
    }

    /**
     * PSR-16 forbids {}()/\@: in keys. Compound keys include ":" and user ids may include "@".
     */
    private function cacheKey(string $policy, string $compound): string
    {
        return 'dirl_'.hash('sha256', $policy."\n".$compound);
    }

    private function compoundKey(string $key, ?string $ipHash, ?string $userId, ?string $deviceId): string
    {
        $ip = $ipHash ?? 'anon';
        $user = $userId ?? 'anon';
        $device = $deviceId ?? 'anon';

        return match ($key) {
            'user' => 'u:'.$user,
            'device' => 'd:'.$device,
            'device_ip' => 'di:'.$device.':'.$ip,
            'user_device' => 'ud:'.$user.':'.$device,
            default => 'ip:'.$ip,
        };
    }

    /**
     * Converts a rate-limit interval ("15 minutes", "1 day", "PT30S", ...) to seconds.
     *
     * Accepts the same formats as Symfony RateLimiter (relative date strings and ISO 8601 durations).
     *
     * @throws InvalidArgumentException when the interval cannot be parsed or is not positive
     */
    public static function intervalToSeconds(string $interval): int
    {
        $value = trim($interval);
        try {
            $parsed = 1 === preg_match('/^P/i', $value)
                ? new DateInterval(strtoupper($value))
                : @DateInterval::createFromDateString($value);
        } catch (Throwable) {
            $parsed = false;
        }
        if (!$parsed instanceof DateInterval) {
            throw new InvalidArgumentException(\sprintf('Invalid rate limit interval "%s".', $interval));
        }

        $epoch = new DateTimeImmutable('@0');
        $seconds = $epoch->add($parsed)->getTimestamp() - $epoch->getTimestamp();
        if ($seconds < 1) {
            throw new InvalidArgumentException(\sprintf('Invalid rate limit interval "%s": it must be a positive duration.', $interval));
        }

        return $seconds;
    }
}
