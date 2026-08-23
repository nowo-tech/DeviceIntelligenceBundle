<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Privacy;

final class IpHasher
{
    public static function hash(?string $ip, string $salt, bool $enabled): ?IpHash
    {
        if (null === $ip || '' === $ip || !$enabled) {
            return null;
        }

        return IpHash::hmac($ip, $salt);
    }
}
