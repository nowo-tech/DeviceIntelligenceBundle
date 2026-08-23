<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk;

use Nowo\DeviceIntelligence\Exception\InvalidValueException;

use function sprintf;

final readonly class RiskScore
{
    public function __construct(public int $value)
    {
        if ($value < 0 || $value > 100) {
            throw new InvalidValueException(sprintf('Risk score must be 0..100, got %d.', $value));
        }
    }

    public static function clamp(int $value): self
    {
        return new self(max(0, min(100, $value)));
    }
}
