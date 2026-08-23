<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Velocity;

use DateInterval;
use DateTimeImmutable;
use Exception;
use Nowo\DeviceIntelligence\Exception\InvalidValueException;

final readonly class TimeWindow
{
    public function __construct(public DateInterval $interval)
    {
    }

    public static function parse(string $spec): self
    {
        $spec = trim($spec);
        if (preg_match('/^(\d+)\s+hours?$/i', $spec, $m)) {
            return new self(new DateInterval('PT' . $m[1] . 'H'));
        }
        if (preg_match('/^(\d+)\s+minutes?$/i', $spec, $m)) {
            return new self(new DateInterval('PT' . $m[1] . 'M'));
        }
        if (preg_match('/^(\d+)\s+days?$/i', $spec, $m)) {
            return new self(new DateInterval('P' . $m[1] . 'D'));
        }
        try {
            return new self(new DateInterval($spec));
        } catch (Exception $e) {
            throw new InvalidValueException('Invalid time window: ' . $spec, 0, $e);
        }
    }

    public function seconds(): int
    {
        $now   = new DateTimeImmutable('@0');
        $later = $now->add($this->interval);

        return max(1, $later->getTimestamp() - $now->getTimestamp());
    }
}
