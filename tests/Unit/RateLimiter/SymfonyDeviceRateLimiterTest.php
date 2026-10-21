<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\RateLimiter;

use InvalidArgumentException;
use Nowo\DeviceIntelligenceBundle\RateLimiter\SymfonyDeviceRateLimiter;
use Nowo\DeviceIntelligenceBundle\Tests\Support\ProcessedConfig;
use PHPUnit\Framework\Attributes\DataProvider;
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
        self::assertTrue($limiter->consume('login', 'ip', 'ip', null, null, 5, '1 day'));
    }

    public function testUnsupportedIntervalThrowsInsteadOfDefaultingTo60Seconds(): void
    {
        $limiter = new SymfonyDeviceRateLimiter(
            ProcessedConfig::object(),
            new Psr16Cache(new ArrayAdapter()),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid rate limit interval "weird"');
        $limiter->consume('login', 'ip', 'ip', null, null, 5, 'weird');
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function validIntervalProvider(): iterable
    {
        yield 'seconds' => ['60 seconds', 60];
        yield 'minute' => ['1 minute', 60];
        yield 'minutes' => ['15 minutes', 900];
        yield 'hours' => ['24 hours', 86400];
        yield 'day' => ['1 day', 86400];
        yield 'week' => ['1 week', 604800];
        yield 'iso seconds' => ['PT30S', 30];
        yield 'iso minutes' => ['pt5m', 300];
        yield 'iso day' => ['P1D', 86400];
        yield 'iso mixed' => ['PT1H30M', 5400];
    }

    #[DataProvider('validIntervalProvider')]
    public function testIntervalToSeconds(string $interval, int $expected): void
    {
        self::assertSame($expected, SymfonyDeviceRateLimiter::intervalToSeconds($interval));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIntervalProvider(): iterable
    {
        yield 'garbage' => ['weird'];
        yield 'empty' => [''];
        yield 'zero' => ['0 seconds'];
        yield 'negative' => ['-1 minute'];
        yield 'bad iso' => ['PTXS'];
    }

    #[DataProvider('invalidIntervalProvider')]
    public function testIntervalToSecondsRejectsInvalid(string $interval): void
    {
        $this->expectException(InvalidArgumentException::class);
        SymfonyDeviceRateLimiter::intervalToSeconds($interval);
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
