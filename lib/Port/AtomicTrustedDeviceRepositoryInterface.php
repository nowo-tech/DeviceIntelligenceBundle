<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Port;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Exception\LookAlikeIdentifierException;
use Nowo\DeviceIntelligence\User\UserIdentifier;

/**
 * Optional, for a {@see TrustedDeviceRepositoryInterface} adapter that revokes a trust in one write (REQ-RUNTIME-003
 * rule 4, 1.10): only the revocation time is written, so a revocation never writes back the expiry, label or grant
 * of a trust read before a concurrent change. When the adapter implements it, DeviceManager::revoke() uses it;
 * otherwise it falls back to findActive() + save().
 */
interface AtomicTrustedDeviceRepositoryInterface extends TrustedDeviceRepositoryInterface
{
    /**
     * Sets the revocation time of the user's trust on the device to $at. When there is no such trust, stores a revoked
     * one (trusted and revoked at $at, no expiry, label $label).
     *
     * @throws LookAlikeIdentifierException when a look-alike user's row blocks this one (folding unique key)
     */
    public function revoke(DeviceId $deviceId, UserIdentifier $user, DateTimeImmutable $at, string $label): void;
}
