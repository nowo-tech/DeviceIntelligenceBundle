<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Messenger;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Device\DeviceStatus;
use Nowo\DeviceIntelligence\Device\Stability;
use Nowo\DeviceIntelligence\Infrastructure\FrozenClock;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryObservationRepository;
use Nowo\DeviceIntelligence\Matching\CandidateIndexKey;
use Nowo\DeviceIntelligence\Matching\Confidence;
use Nowo\DeviceIntelligence\Observation\DeviceObservation;
use Nowo\DeviceIntelligence\Observation\ObservationId;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligenceBundle\Messenger\CleanupHandler;
use Nowo\DeviceIntelligenceBundle\Messenger\CleanupMessage;
use Nowo\DeviceIntelligenceBundle\Messenger\RecalculateStabilityHandler;
use Nowo\DeviceIntelligenceBundle\Messenger\RecalculateStabilityMessage;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class HandlersTest extends TestCase
{
    public function testCleanupDeletesOldObservations(): void
    {
        $now          = new DateTimeImmutable('2026-08-23T12:00:00Z');
        $observations = new InMemoryObservationRepository();
        $deviceId     = DeviceId::generate($now);
        $old          = new DeviceObservation(
            ObservationId::generate($now->modify('-200 days')),
            $deviceId,
            $now->modify('-200 days'),
            1,
            '1.0.0',
            null,
            null,
            null,
            null,
            null,
            null,
            SignalBag::empty(),
            10,
            false,
            1,
        );
        $fresh = new DeviceObservation(
            ObservationId::generate($now),
            $deviceId,
            $now,
            1,
            '1.0.0',
            null,
            null,
            null,
            null,
            null,
            null,
            SignalBag::empty(),
            10,
            false,
            1,
        );
        $observations->save($old);
        $observations->save($fresh);

        $deleted = (new CleanupHandler($observations, new FrozenClock($now)))(new CleanupMessage('P180D'));

        self::assertSame(1, $deleted);
        self::assertNull($observations->find($old->id));
        self::assertNotNull($observations->find($fresh->id));
    }

    public function testRecalculateUpdatesStabilityWhenHistoryExists(): void
    {
        $now          = new DateTimeImmutable('2026-08-23T12:00:00Z');
        $devices      = new InMemoryDeviceRepository();
        $observations = new InMemoryObservationRepository();
        $id           = DeviceId::generate($now);
        $device       = new Device(
            $id,
            $now,
            $now,
            2,
            new Confidence(0.8),
            new Stability(0.5),
            DeviceStatus::Active,
            new CandidateIndexKey('linux', 'chrome', 'other', 'desktop', 'UTC', 'abcd'),
            'Chrome on Linux',
            [],
            SignalBag::empty(),
        );
        $devices->save($device);
        $observations->save(new DeviceObservation(
            ObservationId::generate($now->modify('-1 hour')),
            $id,
            $now->modify('-1 hour'),
            1,
            '1.0.0',
            null,
            null,
            null,
            null,
            null,
            null,
            SignalBag::empty(),
            10,
            false,
            1,
        ));
        $observations->save(new DeviceObservation(
            ObservationId::generate($now),
            $id,
            $now,
            1,
            '1.0.0',
            null,
            null,
            null,
            null,
            null,
            null,
            SignalBag::empty(),
            10,
            false,
            1,
        ));

        $updated = (new RecalculateStabilityHandler($devices, $observations))(new RecalculateStabilityMessage($id->value));

        self::assertSame(1, $updated);
        $saved = $devices->find($id);
        self::assertNotNull($saved);
        self::assertNotSame(0.5, $saved->stability());
    }

    public function testRecalculateNoopsWithoutHistory(): void
    {
        $now     = new DateTimeImmutable('2026-08-23T12:00:00Z');
        $handler = new RecalculateStabilityHandler(new InMemoryDeviceRepository(), new InMemoryObservationRepository());

        self::assertSame(0, $handler(new RecalculateStabilityMessage('01ARZ3NDEKTSV4RRFFQ69G5FAV')));
    }
}
