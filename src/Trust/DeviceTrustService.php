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
    /**
     * Creates the device trust service.
     */
    public function __construct(
        private DeviceManager $devices,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * Marks the device as trusted by the user (optional expiry and label).
     */
    public function trust(Device $device, UserIdentifier $user, ?DateTimeImmutable $expiresAt = null, ?string $label = null): void
    {
        $this->devices->trust($device, $user, $expiresAt, $label);
        $this->dispatcher->dispatch(new DeviceTrustedEvent($device, $user));
    }

    /**
     * Revokes the trust of the user on the device.
     */
    public function revoke(Device $device, UserIdentifier $user): void
    {
        $this->devices->revoke($device, $user);
        $this->dispatcher->dispatch(new DeviceRevokedEvent($device, $user));
    }

    /**
     * Whether it is trusted.
     */
    public function isTrusted(Device $device, UserIdentifier $user): bool
    {
        return $this->devices->isTrusted($device, $user);
    }
}
