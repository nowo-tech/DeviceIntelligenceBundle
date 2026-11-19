<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Tests\Unit;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\AnalysisInput;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Device\DeviceManager;
use Nowo\DeviceIntelligence\Device\DeviceStatus;
use Nowo\DeviceIntelligence\DeviceIntelligence;
use Nowo\DeviceIntelligence\Infrastructure\FrozenClock;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceUserRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryObservationRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryTrustedDeviceRepository;
use Nowo\DeviceIntelligence\Matching\Candidate\RepositoryCandidateProvider;
use Nowo\DeviceIntelligence\Port\AtomicDeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Port\AtomicDeviceUserRepositoryInterface;
use Nowo\DeviceIntelligence\Port\AtomicTrustedDeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Port\DeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Risk\RiskAssessment;
use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Risk\RiskEngine;
use Nowo\DeviceIntelligence\Risk\RiskEngineInterface;
use Nowo\DeviceIntelligence\Signal\Normalizer\SignalNormalizerRegistry;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligence\Signal\SignalFactory;
use Nowo\DeviceIntelligence\User\DeviceUserRelation;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligenceBundle\Tests\Support\Scenario;
use Nowo\TransactionContract\TransactionRunnerInterface;
use PHPUnit\Framework\TestCase;

/**
 * 1.10 (REQ-RUNTIME-003 rules 1 and 4): adapters that implement the atomic ports get one write per change (login count
 * and revocation in storage, observed device by increment), and the analysis writes the device and its observation as
 * one unit through the runner.
 */
final class AtomicWritesTest extends TestCase
{
    public function testDeviceManagerUsesTheAtomicPorts(): void
    {
        $now = new DateTimeImmutable('2026-11-19 10:00:00');
        $device = Scenario::device();
        $user = new UserIdentifier('alice');
        $stored = new DeviceUserRelation($device->id, $user, $now->modify('-1 day'), $now, 8);

        $users = $this->createMock(AtomicDeviceUserRepositoryInterface::class);
        $users->expects(self::never())->method('save');
        $users->expects(self::once())->method('recordLogin')->with($device->id, $user, $now)->willReturn($stored);
        $trusts = $this->createMock(AtomicTrustedDeviceRepositoryInterface::class);
        $trusts->expects(self::never())->method('save');
        $trusts->expects(self::never())->method('findActive');
        $trusts->expects(self::once())->method('revoke')->with($device->id, $user, $now, $device->label);

        $manager = new DeviceManager(new InMemoryDeviceRepository(), $users, $trusts, new FrozenClock($now));
        self::assertSame($stored, $manager->associate($device, $user));
        $manager->revoke($device, $user);
    }

    public function testTheAnalysisWritesOneUnitThroughTheRunner(): void
    {
        $devices = new InMemoryDeviceRepository();
        $observations = new InMemoryObservationRepository();
        $runner = new class implements TransactionRunnerInterface {
            public int $runs = 0;

            public function run(callable $operation): mixed
            {
                ++$this->runs;

                return $operation();
            }
        };
        $engine = new DeviceIntelligence($devices, $observations, new InMemoryDeviceUserRepository(), new InMemoryTrustedDeviceRepository(), transactions: $runner);

        $analysis = $engine->analyze(new AnalysisInput(new DateTimeImmutable('2026-11-19 10:00:00'), SignalBag::empty(), '10.0.0.1', 'Mozilla/5.0 Firefox/128.0'));

        self::assertSame(1, $runner->runs);
        self::assertNotNull($devices->find($analysis->device()->id));
        self::assertNotNull($observations->find($analysis->observation()->id));
    }

    public function testAMatchedDeviceIsSavedAsObserved(): void
    {
        $now = new DateTimeImmutable('2026-11-19 10:00:00');
        $memory = new InMemoryDeviceRepository();
        $devices = $this->createMock(AtomicDeviceRepositoryInterface::class);
        $devices->method('find')->willReturnCallback($memory->find(...));
        $devices->method('findCandidates')->willReturnCallback($memory->findCandidates(...));
        $saved = [];
        $devices->method('save')->willReturnCallback(static function ($device) use ($memory, &$saved): void {
            $saved[] = 'save';
            $memory->save($device);
        });
        $devices->method('saveObserved')->willReturnCallback(static function ($device) use ($memory, &$saved): void {
            $saved[] = 'saveObserved';
            $memory->save($device);
        });
        $engine = DeviceIntelligence::create($devices, new InMemoryObservationRepository(), new InMemoryDeviceUserRepository(), new InMemoryTrustedDeviceRepository());
        $signals = self::matchingSignals($now);

        $first = $engine->analyze(new AnalysisInput($now, $signals, '10.0.0.1', 'Mozilla/5.0 Chrome/143.0.0.0'));
        $second = $engine->analyze(new AnalysisInput($now->modify('+1 minute'), $signals, '10.0.0.1', 'Mozilla/5.0 Chrome/143.0.0.0'));

        self::assertTrue($first->match()->isNewDevice());
        self::assertFalse($second->match()->isNewDevice());
        self::assertSame(['save', 'saveObserved'], $saved);
    }

    /**
     * 1.10.1 (audit UE-DI-1): an adapter without the atomic port gets the device read again inside the write unit, so a
     * status (and metadata) the host changed during the risk assessment is kept, and the count adds one to the stored
     * count, not to the one read for the matching.
     */
    public function testWithoutTheAtomicPortTheHostsStatusChangeDuringTheAssessmentIsKept(): void
    {
        $now = new DateTimeImmutable('2026-11-19 10:00:00');
        $devices = new InMemoryDeviceRepository();
        $risk = new class(RiskEngine::defaults(), $devices) implements RiskEngineInterface {
            public bool $revoke = false;

            public function __construct(private RiskEngineInterface $inner, private InMemoryDeviceRepository $devices)
            {
            }

            public function assess(RiskContext $context): RiskAssessment
            {
                $stored = $this->revoke ? $this->devices->find($context->device->id) : null;
                if (null !== $stored) {
                    // the host revokes the device (and another analysis counted once) while this one is assessed
                    $this->devices->save(new Device($stored->id, $stored->firstSeenAt, $stored->lastSeenAt, $stored->observationCount + 1, $stored->confidence, $stored->stability, DeviceStatus::Revoked, $stored->indexKey, $stored->label, ['revoked_by' => 'host'], $stored->lastSignals));
                }

                return $this->inner->assess($context);
            }
        };
        $runner = new class implements TransactionRunnerInterface {
            public int $runs = 0;

            public function run(callable $operation): mixed
            {
                ++$this->runs;

                return $operation();
            }
        };
        $engine = new DeviceIntelligence($devices, new InMemoryObservationRepository(), new InMemoryDeviceUserRepository(), new InMemoryTrustedDeviceRepository(), candidates: new RepositoryCandidateProvider($devices), normalizers: SignalNormalizerRegistry::defaults(), risk: $risk, transactions: $runner);
        $signals = self::matchingSignals($now);

        $first = $engine->analyze(new AnalysisInput($now, $signals, '10.0.0.1', 'Mozilla/5.0 Chrome/143.0.0.0'));
        $risk->revoke = true;
        $second = $engine->analyze(new AnalysisInput($now->modify('+1 minute'), $signals, '10.0.0.1', 'Mozilla/5.0 Chrome/143.0.0.0'));

        self::assertFalse($second->match()->isNewDevice());
        self::assertSame(2, $runner->runs);
        $stored = $devices->find($first->device()->id);
        self::assertNotNull($stored);
        self::assertSame(DeviceStatus::Revoked, $stored->status);
        self::assertSame(['revoked_by' => 'host'], $stored->metadata);
        self::assertSame(3, $stored->observationCount, 'one by the first analysis, one meanwhile, one by this one');
        self::assertEquals($now->modify('+1 minute'), $stored->lastSeenAt);
    }

    /**
     * Without the atomic port, a matched device deleted before the write (erasure, cleanup) is stored again with the
     * observation, as the atomic port does.
     */
    public function testWithoutTheAtomicPortADeviceDeletedMeanwhileIsStoredAgain(): void
    {
        $now = new DateTimeImmutable('2026-11-19 10:00:00');
        $memory = new InMemoryDeviceRepository();
        $devices = $this->createMock(DeviceRepositoryInterface::class);
        $devices->method('findCandidates')->willReturnCallback($memory->findCandidates(...));
        $devices->method('find')->willReturn(null);
        $saved = [];
        $devices->method('save')->willReturnCallback(static function (Device $device) use ($memory, &$saved): void {
            $saved[] = $device;
            $memory->save($device);
        });
        $engine = DeviceIntelligence::create($devices, new InMemoryObservationRepository(), new InMemoryDeviceUserRepository(), new InMemoryTrustedDeviceRepository());
        $signals = self::matchingSignals($now);

        $engine->analyze(new AnalysisInput($now, $signals, '10.0.0.1', 'Mozilla/5.0 Chrome/143.0.0.0'));
        $second = $engine->analyze(new AnalysisInput($now->modify('+1 minute'), $signals, '10.0.0.1', 'Mozilla/5.0 Chrome/143.0.0.0'));

        self::assertFalse($second->match()->isNewDevice());
        self::assertCount(2, $saved);
        self::assertSame($second->device(), $saved[1]);
    }

    private static function matchingSignals(DateTimeImmutable $now): SignalBag
    {
        return SignalFactory::bagFromClient([
            'platform' => ['value' => 'MacIntel', 'quality' => 1],
            'canvas' => ['value' => 'aabbccddeeff0011', 'quality' => 0.95],
            'webgl' => ['value' => ['vendor' => 'Apple', 'renderer' => 'Apple GPU'], 'quality' => 0.9],
            'screen' => ['value' => ['width' => 1440, 'height' => 900], 'quality' => 1],
            'timezone' => ['value' => 'Europe/Madrid', 'quality' => 1],
            'client_hints' => ['value' => ['brands' => [['brand' => 'Google Chrome', 'version' => '143']], 'platform' => 'macOS'], 'quality' => 0.9],
            'hardware_concurrency' => ['value' => 8, 'quality' => 1],
            'audio' => ['value' => '1122334455667788', 'quality' => 0.9],
        ], $now);
    }
}
