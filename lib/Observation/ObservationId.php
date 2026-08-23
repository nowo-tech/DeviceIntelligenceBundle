<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Observation;

use DateTimeInterface;
use Nowo\DeviceIntelligence\Device\Ulid;
use Nowo\DeviceIntelligence\Exception\InvalidValueException;
use Stringable;

use function sprintf;

final readonly class ObservationId implements Stringable
{
    public function __construct(public string $value)
    {
        if (!Ulid::isValid($value)) {
            throw new InvalidValueException(sprintf('Invalid observation ULID "%s".', $value));
        }
    }

    public static function generate(DateTimeInterface $now): self
    {
        return new self(Ulid::generate($now));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
