<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Infrastructure;

use Psr\Clock\ClockInterface;

/**
 * Request-safe clock used when Symfony Clock is not registered.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
