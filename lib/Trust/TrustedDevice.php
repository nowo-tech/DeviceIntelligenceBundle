<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Trust;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\User\UserIdentifier;

final readonly class TrustedDevice
{
    public function __construct(
        public DeviceId $deviceId,
        public UserIdentifier $userIdentifier,
        public DateTimeImmutable $trustedAt,
        public ?DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $revokedAt,
        public string $label,
        public string $grantedBy = 'user',
    ) {
    }

    public function isActive(DateTimeImmutable $now): bool
    {
        if ($this->revokedAt !== null) {
            return false;
        }

        return !($this->expiresAt !== null && $this->expiresAt <= $now)

        ;
    }
}
