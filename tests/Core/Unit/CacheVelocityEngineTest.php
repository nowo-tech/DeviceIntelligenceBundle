<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Tests\Unit;

use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Matching\CandidateIndexKey;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligence\Velocity\CacheVelocityEngine;
use Nowo\DeviceIntelligence\Velocity\TimeWindow;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class CacheVelocityEngineTest extends TestCase
{
    public function testIncrementAndCount(): void
    {
        $engine = new CacheVelocityEngine(new Psr16Cache(new ArrayAdapter()), 't.');
        $device = Device::fromNew(
            DeviceId::generate(new \DateTimeImmutable()),
            new \DateTimeImmutable(),
            CandidateIndexKey::unknown(),
            SignalBag::empty(),
            'x',
        );
        $engine->increment('k', $device, 2);
        self::assertSame(2, $engine->count('k', $device, TimeWindow::parse('1 hours')));
        self::assertSame(0, $engine->count('other', $device, TimeWindow::parse('1 hours')));
    }

    public function testIncrementDropsTimestampsOlderThanTheTtl(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $engine = new CacheVelocityEngine($cache, 't.');
        $device = Device::fromNew(
            DeviceId::generate(new \DateTimeImmutable()),
            new \DateTimeImmutable(),
            CandidateIndexKey::unknown(),
            SignalBag::empty(),
            'x',
        );
        $key = 't.k.'.$device->id->value;
        $cache->set($key, [time() - 86400 * 8, time() - 86400 * 9, time() - 60]);

        $engine->increment('k', $device);

        $payload = $cache->get($key);
        self::assertIsArray($payload);
        self::assertCount(2, $payload);
        self::assertSame(2, $engine->count('k', $device, TimeWindow::parse('1 hours')));
    }
}
