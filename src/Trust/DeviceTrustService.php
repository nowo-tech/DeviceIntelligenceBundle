<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Trust;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Device\DeviceManager;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligenceBundle\Event\DeviceRevokedEvent;
use Nowo\DeviceIntelligenceBundle\Event\DeviceTrustedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Explicit trust/revoke API that dispatches Symfony events. Login never calls this.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DeviceTrustService
{
    public function __construct(
        private DeviceManager $devices,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function trust(Device $device, UserIdentifier $user, ?DateTimeImmutable $expiresAt = null, ?string $label = null): void
    {
        $this->devices->trust($device, $user, $expiresAt, $label);
        $this->dispatcher->dispatch(new DeviceTrustedEvent($device, $user));
    }

    public function revoke(Device $device, UserIdentifier $user): void
    {
        $this->devices->revoke($device, $user);
        $this->dispatcher->dispatch(new DeviceRevokedEvent($device, $user));
    }

    public function isTrusted(Device $device, UserIdentifier $user): bool
    {
        return $this->devices->isTrusted($device, $user);
    }
}
