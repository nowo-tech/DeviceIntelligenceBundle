<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Tests\Unit;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\AnalysisInput;
use Nowo\DeviceIntelligence\Device\DefaultDeviceLabeler;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Device\DeviceStatus;
use Nowo\DeviceIntelligence\Device\Stability;
use Nowo\DeviceIntelligence\Device\Ulid;
use Nowo\DeviceIntelligence\Exception\InvalidValueException;
use Nowo\DeviceIntelligence\Matching\CandidateIndexKey;
use Nowo\DeviceIntelligence\Matching\Confidence;
use Nowo\DeviceIntelligence\Matching\MatchingWeights;
use Nowo\DeviceIntelligence\Port\NullGeoIpProvider;
use Nowo\DeviceIntelligence\Port\NullMetricsRecorder;
use Nowo\DeviceIntelligence\Privacy\IpHash;
use Nowo\DeviceIntelligence\Privacy\IpHasher;
use Nowo\DeviceIntelligence\Privacy\PrivacyContext;
use Nowo\DeviceIntelligence\Privacy\PrivacyMode;
use Nowo\DeviceIntelligence\Privacy\PrivacyProcessor;
use Nowo\DeviceIntelligence\Risk\RiskLevels;
use Nowo\DeviceIntelligence\Signal\EnhancementLevel;
use Nowo\DeviceIntelligence\Signal\Quality;
use Nowo\DeviceIntelligence\Signal\Server\NullNetworkSignalProvider;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligence\Signal\SignalName;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligence\Velocity\InMemoryVelocityEngine;
use Nowo\DeviceIntelligence\Velocity\TimeWindow;
use PHPUnit\Framework\TestCase;

use function strlen;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ValueObjectsTest extends TestCase
{
    public function testUlidAndDeviceId(): void
    {
        $now = new DateTimeImmutable();
        $id  = DeviceId::generate($now);
        self::assertTrue(Ulid::isValid($id->value));
        self::assertTrue($id->equals($id));
        self::assertSame($id->value, (string) $id);
        $this->expectException(InvalidValueException::class);
        new DeviceId('not-a-ulid');
    }

    public function testConfidenceStabilityQuality(): void
    {
        self::assertSame(1.0, Confidence::clamp(2.0)->value);
        self::assertSame(0.0, Stability::clamp(-1.0)->value);
        self::assertSame(0.5, (new Quality(0.5))->value);
        $this->expectException(InvalidValueException::class);
        new Confidence(2.0);
    }

    public function testPrivacyAndIp(): void
    {
        self::assertSame(['audio', 'canvas', 'webgl', 'fonts'], PrivacyMode::Strict->blockedHighEntropyCollectors());
        self::assertSame([], PrivacyMode::Balanced->blockedHighEntropyCollectors());
        self::assertNull(IpHasher::hash(null, 's', true));
        $hash = IpHasher::hash('1.2.3.4', 's', true);
        self::assertNotNull($hash);
        self::assertSame($hash->value, (string) $hash);
        $this->expectException(InvalidValueException::class);
        new IpHash('zz');
    }

    public function testMatchingWeightsAndIndex(): void
    {
        $weights = MatchingWeights::defaults();
        self::assertGreaterThan(0.0, $weights->weightFor(SignalName::Canvas));
        self::assertGreaterThan(0.0, $weights->weightFor(SignalName::HardwareConcurrency));
        self::assertSame(0.0, $weights->weightFor(SignalName::Language));
        $key = CandidateIndexKey::unknown();
        self::assertSame('other', $key->osFamily);
        self::assertSame('', CandidateIndexKey::blockingKeyFrom([]));
        self::assertSame(4, strlen(CandidateIndexKey::blockingKeyFrom(['a' => '1'])));
        self::assertSame('x', $key->digestFor(SignalName::Timezone, 'x'));
        self::assertNull($key->digestFor(SignalName::Timezone, []));
    }

    public function testRiskLevelsEnhancementUserTimeWindow(): void
    {
        $levels = new RiskLevels();
        self::assertSame('low', $levels->levelFor(0)->value);
        self::assertSame('medium', $levels->levelFor(30)->value);
        self::assertSame('high', $levels->levelFor(65)->value);
        self::assertSame('critical', $levels->levelFor(90)->value);
        self::assertSame(0, EnhancementLevel::of(SignalBag::empty()));
        $user = new UserIdentifier('alice');
        self::assertTrue($user->equals(new UserIdentifier('alice')));
        self::assertSame('alice', (string) $user);
        self::assertGreaterThanOrEqual(3600, TimeWindow::parse('1 hours')->seconds());
        self::assertGreaterThanOrEqual(60, TimeWindow::parse('2 minutes')->seconds());
        self::assertGreaterThanOrEqual(86400, TimeWindow::parse('1 days')->seconds());
        self::assertGreaterThanOrEqual(1, TimeWindow::parse('PT1S')->seconds());
        $this->expectException(InvalidValueException::class);
        TimeWindow::parse('nope');
    }

    public function testNullPortsAndLabelerAndVelocity(): void
    {
        self::assertNull((new NullGeoIpProvider())->locate('1.1.1.1'));
        $metrics = new NullMetricsRecorder();
        $metrics->increment('x');
        $metrics->timing('y', 1.0);
        $net = new NullNetworkSignalProvider();
        self::assertSame([], iterator_to_array($net->collect(new AnalysisInput(new DateTimeImmutable()))));
        $label = (new DefaultDeviceLabeler())->label(SignalBag::empty());
        self::assertStringContainsString('on', $label);
        $velocity = new InMemoryVelocityEngine();
        $id       = DeviceId::generate(new DateTimeImmutable());
        $device   = Device::fromNew(
            $id,
            new DateTimeImmutable(),
            CandidateIndexKey::unknown(),
            SignalBag::empty(),
            'Browser on unknown OS',
        );
        $velocity->increment('login', $device);
        self::assertGreaterThanOrEqual(1, $velocity->count('login', $device, TimeWindow::parse('1 hours')));
        self::assertContains(DeviceStatus::Active, DeviceStatus::cases());
        $processor = new PrivacyProcessor();
        $strict    = $processor->process(
            SignalBag::empty(),
            new PrivacyContext(PrivacyMode::Strict, false, true, false, false),
        );
        self::assertFalse($strict->has(SignalName::UserAgent));
    }
}
