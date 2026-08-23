<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Port;

use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\User\DeviceUserRelation;
use Nowo\DeviceIntelligence\User\UserIdentifier;

interface DeviceUserRepositoryInterface
{
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
