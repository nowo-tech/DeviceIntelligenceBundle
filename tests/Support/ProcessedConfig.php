<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Support;

use Nowo\DeviceIntelligenceBundle\Config\DeviceIntelligenceConfig;
use Nowo\DeviceIntelligenceBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ProcessedConfig
{
    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    public static function array(array $override = []): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$override]);
    }

    /**
     * @param array<string, mixed> $override
     */
    public static function object(array $override = []): DeviceIntelligenceConfig
    {
        return new DeviceIntelligenceConfig(self::array($override));
    }
}
