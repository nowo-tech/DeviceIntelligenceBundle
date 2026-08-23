<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Matching;

use Nowo\DeviceIntelligence\Exception\InvalidValueException;

final readonly class Confidence
{
    public function __construct(public float $value)
    {
        if (!is_finite($value) || $value < 0.0 || $value > 1.0) {
            throw new InvalidValueException(\sprintf('Confidence must be in [0, 1], got %s.', (string) $value));
        }
    }

    public static function clamp(float $value): self
    {
        return new self(max(0.0, min(1.0, $value)));
    }
}
