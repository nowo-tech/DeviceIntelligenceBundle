<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Event;

use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after an explicit user trust grant.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DeviceTrustedEvent extends Event
{
    public function __construct(
        public Device $device,
        public UserIdentifier $user,
    ) {
    }
}
