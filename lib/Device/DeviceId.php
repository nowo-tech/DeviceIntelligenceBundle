<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Device;

use DateTimeInterface;
use Nowo\DeviceIntelligence\Exception\InvalidValueException;
use Stringable;

use function sprintf;

final readonly class DeviceId implements Stringable
{
    public function __construct(public string $value)
    {
        if (!Ulid::isValid($value)) {
            throw new InvalidValueException(sprintf('Invalid device ULID "%s".', $value));
        }
    }

    public static function generate(DateTimeInterface $now): self
    {
        return new self(Ulid::generate($now));
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
