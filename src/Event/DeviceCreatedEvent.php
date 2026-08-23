<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Event;

use Nowo\DeviceIntelligence\Analysis;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when analyze() created a new device record.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DeviceCreatedEvent extends Event
{
    public function __construct(public Analysis $analysis)
    {
    }
}
