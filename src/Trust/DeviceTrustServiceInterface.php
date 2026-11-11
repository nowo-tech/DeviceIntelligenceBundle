<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Trust;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligenceBundle\Event\DeviceRevokedEvent;
use Nowo\DeviceIntelligenceBundle\Event\DeviceTrustedEvent;

/**
 * Trust API of the bundle (1.7, architecture bar rule 1): mark a device as trusted by a user, revoke it, ask whether it
 * is trusted. Implementations dispatch {@see DeviceTrustedEvent} /
 * {@see DeviceRevokedEvent}. Type-hint this interface; decorate it to audit trust
 * changes.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface DeviceTrustServiceInterface
{
    public function trust(Device $device, UserIdentifier $user, ?DateTimeImmutable $expiresAt = null, ?string $label = null): void;

    public function revoke(Device $device, UserIdentifier $user): void;

    public function isTrusted(Device $device, UserIdentifier $user): bool;
}
