<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Command;

use Nowo\DeviceIntelligence\AnalysisInput;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Device\DeviceManager;
use Nowo\DeviceIntelligence\DeviceIntelligence;
use Nowo\DeviceIntelligence\Infrastructure\FrozenClock;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceUserRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryObservationRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryTrustedDeviceRepository;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligenceBundle\Command\CleanupCommand;
use Nowo\DeviceIntelligenceBundle\Command\DeviceShowCommand;
use Nowo\DeviceIntelligenceBundle\Command\RecalculateCommand;
use Nowo\DeviceIntelligenceBundle\Command\RiskTestCommand;
use Nowo\DeviceIntelligenceBundle\Command\StatsCommand;
use Nowo\DeviceIntelligenceBundle\Command\UserDevicesCommand;
use Nowo\DeviceIntelligenceBundle\Event\AnalyzeService;
use Nowo\DeviceIntelligenceBundle\Messenger\CleanupMessage;
use Nowo\DeviceIntelligenceBundle\Messenger\RecalculateStabilityHandler;
use Nowo\DeviceIntelligenceBundle\Messenger\RecalculateStabilityMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class CommandsTest extends TestCase
{
    public function testCleanupSyncAndAsync(): void
    {
        $observations = new InMemoryObservationRepository();
        $sync = new CommandTester(new CleanupCommand($observations));
        self::assertSame(0, $sync->execute(['--older-than' => 'P30D']));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturnCallback(
            static function (object $message): Envelope {
                self::assertInstanceOf(CleanupMessage::class, $message);

                return new Envelope($message);
            },
        );
        $async = new CommandTester(new CleanupCommand($observations, $bus));
        self::assertSame(0, $async->execute(['--async' => true]));
    }

    public function testStatsWithInMemory(): void
    {
        $devices = new InMemoryDeviceRepository();
        $tester = new CommandTester(new StatsCommand(
            $devices,
            new InMemoryObservationRepository(),
            new InMemoryDeviceUserRepository(),
            new InMemoryTrustedDeviceRepository(),
        ));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Devices', $tester->getDisplay());
    }

    public function testRiskTestEmptyJson(): void
    {
        $engine = DeviceIntelligence::create(
            new InMemoryDeviceRepository(),
            new InMemoryObservationRepository(),
            new InMemoryDeviceUserRepository(),
            new InMemoryTrustedDeviceRepository(),
        );
        $file = tempnam(sys_get_temp_dir(), 'di');
        self::assertIsString($file);
        file_put_contents($file, '{"signals":{}}');
        $tester = new CommandTester(new RiskTestCommand(new AnalyzeService($engine, new EventDispatcher())));
        self::assertSame(0, $tester->execute(['--file' => $file]));
        unlink($file);
        self::assertStringContainsString('Device', $tester->getDisplay());
    }

    public function testDeviceShowMissing(): void
    {
        $now = new \DateTimeImmutable();
        $manager = new DeviceManager(
            new InMemoryDeviceRepository(),
            new InMemoryDeviceUserRepository(),
            new InMemoryTrustedDeviceRepository(),
            new FrozenClock($now),
        );
        $tester = new CommandTester(new DeviceShowCommand($manager, new InMemoryObservationRepository()));
        self::assertSame(1, $tester->execute(['deviceId' => DeviceId::generate($now)->value]));
    }

    public function testUserDevicesEmpty(): void
    {
        $now = new \DateTimeImmutable();
        $manager = new DeviceManager(
            new InMemoryDeviceRepository(),
            new InMemoryDeviceUserRepository(),
            new InMemoryTrustedDeviceRepository(),
            new FrozenClock($now),
        );
        $tester = new CommandTester(new UserDevicesCommand($manager));
        self::assertSame(0, $tester->execute(['user' => 'alice']));
        self::assertStringContainsString('No devices', $tester->getDisplay());
    }

    public function testRecalculateSyncAndAsync(): void
    {
        $handler = new RecalculateStabilityHandler(new InMemoryDeviceRepository(), new InMemoryObservationRepository());
        $sync = new CommandTester(new RecalculateCommand($handler));
        self::assertSame(0, $sync->execute([]));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturnCallback(
            static function (object $message): Envelope {
                self::assertInstanceOf(RecalculateStabilityMessage::class, $message);

                return new Envelope($message);
            },
        );
        $async = new CommandTester(new RecalculateCommand($handler, $bus));
        self::assertSame(0, $async->execute(['--async' => true]));
    }

    public function testAnalyzeInputDto(): void
    {
        $input = new AnalysisInput(new \DateTimeImmutable(), SignalBag::empty());
        self::assertSame(1, $input->schemaVersion);
        self::assertTrue($input->highEntropyConsent);
    }
}
