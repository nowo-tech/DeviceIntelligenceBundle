<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Device;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Port\DeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Port\DeviceUserRepositoryInterface;
use Nowo\DeviceIntelligence\Port\TrustedDeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Trust\TrustedDevice;
use Nowo\DeviceIntelligence\Trust\TrustedDeviceManagerInterface;
use Nowo\DeviceIntelligence\User\DeviceUserRelation;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Psr\Clock\ClockInterface;

use function count;

final class DeviceManager implements DeviceManagerInterface, TrustedDeviceManagerInterface
{
    public function __construct(
        private DeviceRepositoryInterface $devices,
        private DeviceUserRepositoryInterface $users,
        private TrustedDeviceRepositoryInterface $trusts,
        private ClockInterface $clock,
        private ?DateTimeImmutable $defaultExpiration = null,
    ) {
    }

    public function get(DeviceId $id): ?Device
    {
        return $this->devices->find($id);
    }

    public function usersForDevice(Device $device): iterable
    {
        return $this->users->forDevice($device->id);
    }

    public function accountCount(Device $device): int
    {
        return count($this->users->forDevice($device->id));
    }

    public function devicesForUser(UserIdentifier $user): iterable
    {
        $out = [];
        foreach ($this->users->forUser($user) as $rel) {
            $device = $this->devices->find($rel->deviceId);
            if ($device !== null) {
                $out[] = $device;
            }
        }

        return $out;
    }

    public function associate(Device $device, UserIdentifier $user): DeviceUserRelation
    {
        $now      = $this->clock->now();
        $existing = $this->users->find($device->id, $user);
        if ($existing === null) {
            $rel = new DeviceUserRelation($device->id, $user, $now, $now, 1);
        } else {
            $rel = $existing->withLogin($now);
        }
        $this->users->save($rel);

        return $rel;
    }

    public function trust(Device $device, UserIdentifier $user, ?DateTimeImmutable $expiresAt = null, ?string $label = null): void
    {
        $now = $this->clock->now();
        $this->trusts->save(new TrustedDevice(
            $device->id,
            $user,
            $now,
            $expiresAt ?? $this->defaultExpiration,
            null,
            $label ?? $device->label,
            'user',
        ));
    }

    public function revoke(Device $device, UserIdentifier $user): void
    {
        $now      = $this->clock->now();
        $existing = $this->trusts->findActive($device->id, $user, $now);
        if ($existing === null) {
            $existing = new TrustedDevice($device->id, $user, $now, null, $now, $device->label);
        } else {
            $existing = new TrustedDevice(
                $existing->deviceId,
                $existing->userIdentifier,
                $existing->trustedAt,
                $existing->expiresAt,
                $now,
                $existing->label,
                $existing->grantedBy,
            );
        }
        $this->trusts->save($existing);
    }

    public function isTrusted(Device $device, UserIdentifier $user): bool
    {
        return $this->trusts->findActive($device->id, $user, $this->clock->now()) !== null;
    }
}
