<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Event;

use Nowo\DeviceIntelligence\Analysis;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Alias of a newly created device, kept for listeners that subscribe by this name.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class NewDeviceDetectedEvent extends Event
{
    public function __construct(public Analysis $analysis)
    {
    }
}
