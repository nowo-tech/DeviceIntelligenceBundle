<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Attribute;

/**
 * Marks a service as a custom risk rule (tag nowo.device_intelligence.risk_rule).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsDeviceRiskRule
{
    public function __construct(public int $priority = 0)
    {
    }
}
