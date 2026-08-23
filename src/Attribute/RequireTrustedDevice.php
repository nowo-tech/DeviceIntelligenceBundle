<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Attribute;

/**
 * Deny the controller unless the current device is explicitly trusted for the user.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class RequireTrustedDevice
{
}
