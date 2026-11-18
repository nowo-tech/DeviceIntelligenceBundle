<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Port;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Exception\LookAlikeIdentifierException;
use Nowo\DeviceIntelligence\User\DeviceUserRelation;
use Nowo\DeviceIntelligence\User\UserIdentifier;

/**
 * Optional, for a {@see DeviceUserRepositoryInterface} adapter that records a login atomically (REQ-RUNTIME-003 rule 4,
 * 1.10): two logins of the same user on the same device at once both count, and neither fails on the unique key.
 * When the adapter implements it, DeviceManager::associate() uses it; otherwise it falls back to find() + save().
 */
interface AtomicDeviceUserRepositoryInterface extends DeviceUserRepositoryInterface
{
    /**
     * Creates the link (first and last seen $at, one login) or, when it exists, sets its last-seen time to $at and adds
     * one login in storage. Returns the stored relation.
     *
     * @throws LookAlikeIdentifierException when a look-alike user's row blocks this one (folding unique key)
     */
    public function recordLogin(DeviceId $deviceId, UserIdentifier $user, DateTimeImmutable $at): DeviceUserRelation;
}
