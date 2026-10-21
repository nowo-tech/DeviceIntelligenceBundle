<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Trust;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\User\UserIdentifier;

interface TrustedDeviceManagerInterface
{
    public function trust(Device $device, UserIdentifier $user, ?DateTimeImmutable $expiresAt = null, ?string $label = null): void;

    public function revoke(Device $device, UserIdentifier $user): void;

    public function isTrusted(Device $device, UserIdentifier $user): bool;
}
