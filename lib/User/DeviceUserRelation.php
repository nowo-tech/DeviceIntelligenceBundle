<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\User;

use Nowo\DeviceIntelligence\Device\DeviceId;

final readonly class DeviceUserRelation
{
    public function __construct(
        public DeviceId $deviceId,
        public UserIdentifier $userIdentifier,
        public \DateTimeImmutable $firstSeenAt,
        public \DateTimeImmutable $lastSeenAt,
        public int $loginCount,
    ) {
    }

    public function withLogin(\DateTimeImmutable $at): self
    {
        return new self(
            $this->deviceId,
            $this->userIdentifier,
            $this->firstSeenAt,
            $at,
            $this->loginCount + 1,
        );
    }
}
