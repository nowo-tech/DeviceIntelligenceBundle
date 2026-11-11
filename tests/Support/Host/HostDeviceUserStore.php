<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Support\Host;

use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceUserRepository;
use Nowo\DeviceIntelligence\Port\DeviceUserRepositoryInterface;
use Nowo\DeviceIntelligence\User\DeviceUserRelation;
use Nowo\DeviceIntelligence\User\UserIdentifier;

/**
 * A host's own device / user store (kept across requests).
 */
final class HostDeviceUserStore implements DeviceUserRepositoryInterface
{
    public int $writes = 0;

    private readonly InMemoryDeviceUserRepository $rows;

    public function __construct()
    {
        $this->rows = new InMemoryDeviceUserRepository();
    }

    public function save(DeviceUserRelation $relation): void
    {
        ++$this->writes;
        $this->rows->save($relation);
    }

    public function forDevice(DeviceId $deviceId): array
    {
        return $this->rows->forDevice($deviceId);
    }

    public function forUser(UserIdentifier $user): array
    {
        return $this->rows->forUser($user);
    }

    public function find(DeviceId $deviceId, UserIdentifier $user): ?DeviceUserRelation
    {
        return $this->rows->find($deviceId, $user);
    }
}
