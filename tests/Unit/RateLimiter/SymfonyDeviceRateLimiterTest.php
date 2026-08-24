<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\RateLimiter;

use Nowo\DeviceIntelligenceBundle\RateLimiter\SymfonyDeviceRateLimiter;
use Nowo\DeviceIntelligenceBundle\Tests\Support\ProcessedConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SymfonyDeviceRateLimiterTest extends TestCase
{
    public function testConsumeUntilLimit(): void
    {
        $limiter = new SymfonyDeviceRateLimiter(
            ProcessedConfig::object(),
            new Psr16Cache(new ArrayAdapter()),
        );

        self::assertTrue($limiter->consume('collect', 'ip', 'abc', null, null, 2, '60 seconds'));
        self::assertTrue($limiter->consume('collect', 'ip', 'abc', null, null, 2, '60 seconds'));
        self::assertFalse($limiter->consume('collect', 'ip', 'abc', null, null, 2, '60 seconds'));
    }

    public function testCompoundKeys(): void
    {
        $limiter = new SymfonyDeviceRateLimiter(
            ProcessedConfig::object(),
            new Psr16Cache(new ArrayAdapter()),
        );

        self::assertTrue($limiter->consume('login', 'user', 'ip', 'u1', 'd1', 5, '1 minute'));
        self::assertTrue($limiter->consume('login', 'device', 'ip', 'u1', 'd1', 5, '2 hours'));
        self::assertTrue($limiter->consume('login', 'device_ip', 'ip', 'u1', 'd1', 5, 'PT30S'));
        self::assertTrue($limiter->consume('login', 'user_device', 'ip', 'u1', 'd1', 5, 'PT5M'));
        self::assertTrue($limiter->consume('login', 'ip', 'ip', null, null, 5, 'weird'));
    }

    public function testConsumePersistsAcrossLimiterInstances(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $first = new SymfonyDeviceRateLimiter(ProcessedConfig::object(), $cache);
        self::assertTrue($first->consume('coupon', 'device', 'ip', 'user@host', 'd1', 2, '60 seconds'));
        self::assertTrue($first->consume('coupon', 'device', 'ip', 'user@host', 'd1', 2, '60 seconds'));

        $rebooted = new SymfonyDeviceRateLimiter(ProcessedConfig::object(), $cache);
        self::assertFalse($rebooted->consume('coupon', 'device', 'ip', 'user@host', 'd1', 2, '60 seconds'));
    }
}
