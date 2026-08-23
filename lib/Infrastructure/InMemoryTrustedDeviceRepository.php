<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Infrastructure;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Port\TrustedDeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Trust\TrustedDevice;
use Nowo\DeviceIntelligence\User\UserIdentifier;

final class InMemoryTrustedDeviceRepository implements TrustedDeviceRepositoryInterface
{
    /** @var array<string, TrustedDevice> */
    private array $rows = [];

    public function save(TrustedDevice $trust): void
    {
        $this->rows[$trust->deviceId->value . '|' . $trust->userIdentifier->value] = $trust;
    }

    public function findActive(DeviceId $deviceId, UserIdentifier $user, DateTimeImmutable $now): ?TrustedDevice
    {
        $row = $this->rows[$deviceId->value . '|' . $user->value] ?? null;
        if ($row === null || !$row->isActive($now)) {
            return null;
        }

        return $row;
    }

    public function forUser(UserIdentifier $user, DateTimeImmutable $now): array
    {
        $out = [];
        foreach ($this->rows as $row) {
            if ($row->userIdentifier->equals($user) && $row->isActive($now)) {
                $out[] = $row;
            }
        }

        return $out;
    }
}
