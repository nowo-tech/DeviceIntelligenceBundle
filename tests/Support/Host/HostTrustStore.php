<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Support\Host;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryTrustedDeviceRepository;
use Nowo\DeviceIntelligence\Port\TrustedDeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Trust\TrustedDevice;
use Nowo\DeviceIntelligence\User\UserIdentifier;

/**
 * A host's own trust store (kept across requests).
 */
final class HostTrustStore implements TrustedDeviceRepositoryInterface
{
    public int $writes = 0;

    private readonly InMemoryTrustedDeviceRepository $rows;

    public function __construct()
    {
        $this->rows = new InMemoryTrustedDeviceRepository();
    }

    public function save(TrustedDevice $trust): void
    {
        ++$this->writes;
        $this->rows->save($trust);
    }

    public function findActive(DeviceId $deviceId, UserIdentifier $user, DateTimeImmutable $now): ?TrustedDevice
    {
        return $this->rows->findActive($deviceId, $user, $now);
    }

    public function forUser(UserIdentifier $user, DateTimeImmutable $now): array
    {
        return $this->rows->forUser($user, $now);
    }
}
