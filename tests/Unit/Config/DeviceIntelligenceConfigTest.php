<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Config;

use Nowo\DeviceIntelligenceBundle\Tests\Support\ProcessedConfig;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DeviceIntelligenceConfigTest extends TestCase
{
    public function testAccessors(): void
    {
        $config = ProcessedConfig::object();

        self::assertTrue($config->enabled());
        self::assertSame('default', $config->defaultProfileName());
        self::assertArrayHasKey('matching', $config->profile());
        self::assertSame($config->profile(), $config->profile('missing-falls-back'));
        self::assertTrue($config->endpoint()['enabled']);
        self::assertSame('di_obs', $config->tokenCookie()['name']);
        self::assertGreaterThan(0, $config->tokenTtl());
        self::assertFalse($config->observeOnEveryRequest());
        self::assertIsString($config->ipSalt());
        self::assertTrue($config->all()['enabled']);
    }
}
