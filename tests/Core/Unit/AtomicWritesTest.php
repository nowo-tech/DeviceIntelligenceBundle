<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Tests\Unit;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\AnalysisInput;
use Nowo\DeviceIntelligence\Device\DeviceManager;
use Nowo\DeviceIntelligence\DeviceIntelligence;
use Nowo\DeviceIntelligence\Infrastructure\FrozenClock;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceUserRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryObservationRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryTrustedDeviceRepository;
use Nowo\DeviceIntelligence\Port\AtomicDeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Port\AtomicDeviceUserRepositoryInterface;
use Nowo\DeviceIntelligence\Port\AtomicTrustedDeviceRepositoryInterface;
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
        $signals = SignalFactory::bagFromClient([
            'platform' => ['value' => 'MacIntel', 'quality' => 1],
            'canvas' => ['value' => 'aabbccddeeff0011', 'quality' => 0.95],
            'webgl' => ['value' => ['vendor' => 'Apple', 'renderer' => 'Apple GPU'], 'quality' => 0.9],
            'screen' => ['value' => ['width' => 1440, 'height' => 900], 'quality' => 1],
            'timezone' => ['value' => 'Europe/Madrid', 'quality' => 1],
            'client_hints' => ['value' => ['brands' => [['brand' => 'Google Chrome', 'version' => '143']], 'platform' => 'macOS'], 'quality' => 0.9],
            'hardware_concurrency' => ['value' => 8, 'quality' => 1],
            'audio' => ['value' => '1122334455667788', 'quality' => 0.9],
        ], $now);

        $first = $engine->analyze(new AnalysisInput($now, $signals, '10.0.0.1', 'Mozilla/5.0 Chrome/143.0.0.0'));
        $second = $engine->analyze(new AnalysisInput($now->modify('+1 minute'), $signals, '10.0.0.1', 'Mozilla/5.0 Chrome/143.0.0.0'));

        self::assertTrue($first->match()->isNewDevice());
        self::assertFalse($second->match()->isNewDevice());
        self::assertSame(['save', 'saveObserved'], $saved);
    }
}
