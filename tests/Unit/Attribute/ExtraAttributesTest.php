<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Attribute;

use Nowo\DeviceIntelligenceBundle\Attribute\AsDeviceRiskRule;
use Nowo\DeviceIntelligenceBundle\Attribute\DeviceRateLimit;
use Nowo\DeviceIntelligenceBundle\Attribute\RequireTrustedDevice;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ExtraAttributesTest extends TestCase
{
    public function testRateLimitAndTrustedAndRule(): void
    {
        $class = new \ReflectionClass(AttributeProbe::class);

        $rate = $class->getAttributes(DeviceRateLimit::class)[0]->newInstance();
        self::assertSame(10, $rate->limit);
        self::assertSame('1 minute', $rate->interval);
        self::assertSame('device', $rate->policy);

        self::assertCount(1, $class->getAttributes(RequireTrustedDevice::class));

        $rule = $class->getAttributes(AsDeviceRiskRule::class)[0]->newInstance();
        self::assertSame(5, $rule->priority);
    }
}

#[DeviceRateLimit(limit: 10, interval: '1 minute', policy: 'device')]
#[RequireTrustedDevice]
#[AsDeviceRiskRule(priority: 5)]
final class AttributeProbe
{
}
