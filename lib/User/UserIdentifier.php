<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\User;

use Nowo\DeviceIntelligence\Exception\InvalidValueException;

/**
 * Application-agnostic user handle. Never a Doctrine association.
 */
final readonly class UserIdentifier implements \Stringable
{
    public string $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        if ('' === $value || \strlen($value) > 191) {
            throw new InvalidValueException('User identifier must be 1..191 characters.');
        }
        $this->value = $value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
