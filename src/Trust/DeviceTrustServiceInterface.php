<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Trust;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Exception\LookAlikeIdentifierException;
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
    /**
     * @throws LookAlikeIdentifierException when the storage still has a folding unique key and a look-alike user (e.g.
     *                                      `José@` for `jose@`) already holds a row for this device (REQ-SEC-006,
     *                                      1.9.2): the write is refused, never applied to that row. Run the `ALTER` of
     *                                      UPGRADING 1.9.2 to lift it.
     */
    public function trust(Device $device, UserIdentifier $user, ?DateTimeImmutable $expiresAt = null, ?string $label = null): void;

    /**
     * @throws LookAlikeIdentifierException when the storage still has a folding unique key and a look-alike user (e.g.
     *                                      `José@` for `jose@`) already holds a row for this device (REQ-SEC-006,
     *                                      1.9.2): the write is refused, never applied to that row. Run the `ALTER` of
     *                                      UPGRADING 1.9.2 to lift it.
     */
    public function revoke(Device $device, UserIdentifier $user): void;

    public function isTrusted(Device $device, UserIdentifier $user): bool;
}
