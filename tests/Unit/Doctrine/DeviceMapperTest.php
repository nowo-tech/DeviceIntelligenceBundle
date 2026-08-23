<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Doctrine;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Device\DeviceStatus;
use Nowo\DeviceIntelligence\Device\Stability;
use Nowo\DeviceIntelligence\Matching\CandidateIndexKey;
use Nowo\DeviceIntelligence\Matching\Confidence;
use Nowo\DeviceIntelligence\Observation\DeviceObservation;
use Nowo\DeviceIntelligence\Observation\ObservationId;
use Nowo\DeviceIntelligence\Privacy\IpHash;
use Nowo\DeviceIntelligence\Signal\EntropyCategory;
use Nowo\DeviceIntelligence\Signal\Quality;
use Nowo\DeviceIntelligence\Signal\Signal;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligence\Signal\SignalName;
use Nowo\DeviceIntelligence\Signal\SignalSource;
use Nowo\DeviceIntelligence\Trust\TrustedDevice;
use Nowo\DeviceIntelligence\User\DeviceUserRelation;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligenceBundle\Doctrine\DeviceMapper;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DeviceMapperTest extends TestCase
{
    public function testDeviceRoundtrip(): void
    {
        $now = new DateTimeImmutable('2026-01-02T03:04:05+00:00');
        $id  = DeviceId::generate($now);
        $bag = SignalBag::empty()->with(new Signal(
            SignalName::Timezone,
            'Europe/Madrid',
            'Europe/Madrid',
            new Quality(1.0),
            0.85,
            EntropyCategory::Low,
            $now,
            SignalSource::Client,
        ));
        $device = new Device(
            $id,
            $now,
            $now,
            3,
            new Confidence(0.82),
            new Stability(0.71),
            DeviceStatus::Active,
            new CandidateIndexKey('linux', 'chrome', 'other', 'desktop', 'Europe/Madrid', 'abcd'),
            'Chrome on Linux',
            ['note' => 'unit'],
            $bag,
        );

        $mapper = new DeviceMapper();
        $entity = $mapper->toDeviceEntity($device);
        $back   = $mapper->toDevice($entity);

        self::assertSame($device->id->value, $back->id->value);
        self::assertSame($device->observationCount, $back->observationCount);
        self::assertSame($device->confidence->value, $back->confidence->value);
        self::assertSame($device->stability(), $back->stability());
        self::assertSame($device->status, $back->status);
        self::assertSame($device->indexKey->osFamily, $back->indexKey->osFamily);
        self::assertSame($device->label, $back->label);
        self::assertSame($device->metadata, $back->metadata);
        self::assertTrue($back->lastSignals->has(SignalName::Timezone));
    }

    public function testObservationAndRelationsRoundtrip(): void
    {
        $now      = new DateTimeImmutable('2026-03-04T05:06:07+00:00');
        $deviceId = DeviceId::generate($now);
        $obsId    = ObservationId::generate($now);
        $user     = new UserIdentifier('alice@example.test');
        $mapper   = new DeviceMapper();

        $observation = new DeviceObservation(
            $obsId,
            $deviceId,
            $now,
            1,
            '1.0.0',
            IpHash::hmac('203.0.113.10', 'salt'),
            'ES',
            'Chrome',
            null,
            'sess-1',
            $user,
            SignalBag::empty(),
            22,
            false,
            2,
        );
        $back = $mapper->toObservation($mapper->toObservationEntity($observation));
        self::assertSame($obsId->value, $back->id->value);
        self::assertSame($deviceId->value, $back->deviceId->value);
        self::assertSame(22, $back->riskScore);
        self::assertSame('alice@example.test', $back->userIdentifier?->value);

        $rel     = new DeviceUserRelation($deviceId, $user, $now, $now, 4);
        $relBack = $mapper->toUserRelation($mapper->toUserEntity($rel));
        self::assertSame(4, $relBack->loginCount);

        $trust     = new TrustedDevice($deviceId, $user, $now, null, null, 'laptop', 'user');
        $trustBack = $mapper->toTrustedDevice($mapper->toTrustEntity($trust));
        self::assertSame('laptop', $trustBack->label);
        self::assertTrue($trustBack->isActive($now));
    }
}
