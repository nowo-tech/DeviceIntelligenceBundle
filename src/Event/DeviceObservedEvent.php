<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Event;

use Nowo\DeviceIntelligence\Analysis;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after every successful analyze() call.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DeviceObservedEvent extends Event
{
    public function __construct(public Analysis $analysis)
    {
    }
}
