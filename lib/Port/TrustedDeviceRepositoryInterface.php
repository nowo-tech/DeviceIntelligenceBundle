<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Port;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Exception\LookAlikeIdentifierException;
use Nowo\DeviceIntelligence\Trust\TrustedDevice;
use Nowo\DeviceIntelligence\User\UserIdentifier;

interface TrustedDeviceRepositoryInterface
{
    /**
     * Implementations must never write over the row of another (look-alike) user identifier.
     *
     * @throws LookAlikeIdentifierException when a look-alike user's row blocks this one (folding unique key)
     */
    public function save(TrustedDevice $trust): void;

    public function findActive(DeviceId $deviceId, UserIdentifier $user, DateTimeImmutable $now): ?TrustedDevice;

    /**
     * @return list<TrustedDevice>
     */
    public function forUser(UserIdentifier $user, DateTimeImmutable $now): array;
}
