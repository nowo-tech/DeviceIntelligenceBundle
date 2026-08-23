<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Messenger;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class RecalculateStabilityMessage
{
    public function __construct(public ?string $deviceId = null)
    {
    }
}
