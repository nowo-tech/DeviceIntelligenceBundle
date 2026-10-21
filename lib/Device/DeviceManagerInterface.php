<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Device;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\User\DeviceUserRelation;
use Nowo\DeviceIntelligence\User\UserIdentifier;

interface DeviceManagerInterface
{
    public function get(DeviceId $id): ?Device;

    /**
     * @return iterable<DeviceUserRelation>
     */
    public function usersForDevice(Device $device): iterable;

    public function accountCount(Device $device): int;

    /**
     * @return iterable<Device>
     */
    public function devicesForUser(UserIdentifier $user): iterable;

    public function trust(Device $device, UserIdentifier $user, ?DateTimeImmutable $expiresAt = null, ?string $label = null): void;

    public function revoke(Device $device, UserIdentifier $user): void;
}
