<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Attribute;

use Attribute;

/**
 * Deny the controller when the current device risk score exceeds max (0..100).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class DeviceRisk
{
    public function __construct(public int $max = 70)
    {
    }
}
