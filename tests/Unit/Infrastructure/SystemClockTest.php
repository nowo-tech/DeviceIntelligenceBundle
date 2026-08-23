<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Infrastructure;

use Nowo\DeviceIntelligenceBundle\Infrastructure\SystemClock;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SystemClockTest extends TestCase
{
    public function testNowReturnsImmutable(): void
    {
        $clock = new SystemClock();
        $a = $clock->now();
        $b = $clock->now();

        self::assertGreaterThanOrEqual($a->getTimestamp(), $b->getTimestamp());
    }
}
