<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\DependencyInjection;

use Nowo\DeviceIntelligenceBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ConfigurationTest extends TestCase
{
    public function testTreeCompilesWithDefaults(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);

        self::assertTrue($config['enabled']);
        self::assertSame('default', $config['default_profile']);
        self::assertArrayHasKey('default', $config['profiles']);
        self::assertSame(0.75, $config['profiles']['default']['matching']['minimum_confidence']);
        self::assertContains('canvas', $config['profiles']['default']['collectors']);
        self::assertTrue($config['endpoint']['enabled']);
        self::assertSame('device_intelligence_', $config['doctrine']['table_prefix']);
        self::assertFalse($config['profiles']['default']['privacy']['store_raw_ip']);
        self::assertGreaterThan(0.99, array_sum($config['profiles']['default']['matching']['weights']));
        self::assertLessThan(1.01, array_sum($config['profiles']['default']['matching']['weights']));
    }

    public function testLegacyFlatKeysNormalizeIntoDefaultProfile(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'matching' => [
                'minimum_confidence' => 0.81,
            ],
        ]]);

        self::assertSame(0.81, $config['profiles']['default']['matching']['minimum_confidence']);
        self::assertArrayNotHasKey('matching', $config);
    }

    public function testRejectsBadWeights(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must sum to ~1.0');

        (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => [
                'default' => [
                    'matching' => [
                        'weights' => [
                            'audio' => 0.5,
                            'canvas' => 0.5,
                            'webgl' => 0.5,
                        ],
                    ],
                ],
            ],
        ]]);
    }

    public function testRejectsUnknownCollector(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => [
                'default' => [
                    'collectors' => ['audio', 'unknown_probe'],
                ],
            ],
        ]]);
    }
}
