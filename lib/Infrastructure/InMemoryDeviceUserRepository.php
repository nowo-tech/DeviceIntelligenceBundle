<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Infrastructure;

use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Port\DeviceUserRepositoryInterface;
use Nowo\DeviceIntelligence\User\DeviceUserRelation;
use Nowo\DeviceIntelligence\User\UserIdentifier;

final class InMemoryDeviceUserRepository implements DeviceUserRepositoryInterface
{
    /** @var array<string, DeviceUserRelation> */
    private array $rows = [];

    public function save(DeviceUserRelation $relation): void
    {
        $this->rows[$relation->deviceId->value . '|' . $relation->userIdentifier->value] = $relation;
    }

    public function forDevice(DeviceId $deviceId): array
    {
        $out = [];
        foreach ($this->rows as $row) {
            if ($row->deviceId->equals($deviceId)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    public function forUser(UserIdentifier $user): array
    {
        $out = [];
        foreach ($this->rows as $row) {
            if ($row->userIdentifier->equals($user)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    public function find(DeviceId $deviceId, UserIdentifier $user): ?DeviceUserRelation
    {
        return $this->rows[$deviceId->value . '|' . $user->value] ?? null;
    }
}
