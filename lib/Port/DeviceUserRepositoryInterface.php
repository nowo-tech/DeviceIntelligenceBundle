<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Port;

use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Exception\LookAlikeIdentifierException;
use Nowo\DeviceIntelligence\User\DeviceUserRelation;
use Nowo\DeviceIntelligence\User\UserIdentifier;

interface DeviceUserRepositoryInterface
{
    /**
     * Implementations must never write over the row of another (look-alike) user identifier.
     *
     * @throws LookAlikeIdentifierException when a look-alike user's row blocks this one (folding unique key)
     */
    public function save(DeviceUserRelation $relation): void;

    /**
     * @return list<DeviceUserRelation>
     */
    public function forDevice(DeviceId $deviceId): array;

    /**
     * @return list<DeviceUserRelation>
     */
    public function forUser(UserIdentifier $user): array;

    public function find(DeviceId $deviceId, UserIdentifier $user): ?DeviceUserRelation;
}
