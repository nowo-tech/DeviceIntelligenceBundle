<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Trust;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Exception\LookAlikeIdentifierException;
use Nowo\DeviceIntelligence\User\UserIdentifier;

interface TrustedDeviceManagerInterface
{
    /**
     * @throws LookAlikeIdentifierException when the storage still has a folding unique key and a look-alike user (e.g.
     *                                      `José@` for `jose@`) already holds a row for this device (REQ-SEC-006,
     *                                      1.9.2): the write is refused, never applied to that row. Run the `ALTER` of
     *                                      UPGRADING 1.9.2 to lift it.
     */
    public function trust(Device $device, UserIdentifier $user, ?DateTimeImmutable $expiresAt = null, ?string $label = null): void;

    /**
     * @throws LookAlikeIdentifierException when the storage still has a folding unique key and a look-alike user (e.g.
     *                                      `José@` for `jose@`) already holds a row for this device (REQ-SEC-006,
     *                                      1.9.2): the write is refused, never applied to that row. Run the `ALTER` of
     *                                      UPGRADING 1.9.2 to lift it.
     */
    public function revoke(Device $device, UserIdentifier $user): void;

    public function isTrusted(Device $device, UserIdentifier $user): bool;
}
