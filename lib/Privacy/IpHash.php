<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Privacy;

use Nowo\DeviceIntelligence\Exception\InvalidValueException;
use Stringable;

final readonly class IpHash implements Stringable
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[a-f0-9]{16,64}$/', $value) !== 1) {
            throw new InvalidValueException('IP hash must be 16..64 hex characters.');
        }
    }

    public static function hmac(string $ip, string $salt): self
    {
        return new self(hash_hmac('sha256', $ip, $salt));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
