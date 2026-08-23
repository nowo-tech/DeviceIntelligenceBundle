<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Attribute;

use Nowo\DeviceIntelligenceBundle\Attribute\DeviceRisk;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DeviceRiskTest extends TestCase
{
    public function testReflectionReadsMax(): void
    {
        $class = new \ReflectionClass(DeviceRiskProbe::class);
        $attributes = $class->getAttributes(DeviceRisk::class);
        self::assertCount(1, $attributes);
        $instance = $attributes[0]->newInstance();
        self::assertSame(70, $instance->max);

        $method = $class->getMethod('sensitive');
        $methodAttrs = $method->getAttributes(DeviceRisk::class);
        self::assertCount(1, $methodAttrs);
        self::assertSame(40, $methodAttrs[0]->newInstance()->max);
    }
}

#[DeviceRisk(max: 70)]
final class DeviceRiskProbe
{
    #[DeviceRisk(max: 40)]
    public function sensitive(): void
    {
    }
}
