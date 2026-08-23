<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Signal;

use Nowo\DeviceIntelligence\Exception\InvalidValueException;

use function sprintf;

final readonly class Quality
{
    public function __construct(public float $value)
    {
        if (!is_finite($value) || $value < 0.0 || $value > 1.0) {
            throw new InvalidValueException(sprintf('Quality must be in [0, 1], got %s.', (string) $value));
        }
    }
}
