<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Trust;

use Nowo\DeviceIntelligence\AnalysisInput;
use Nowo\DeviceIntelligence\Device\DeviceManager;
use Nowo\DeviceIntelligence\DeviceIntelligence;
use Nowo\DeviceIntelligence\Infrastructure\FrozenClock;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceUserRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryObservationRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryTrustedDeviceRepository;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligenceBundle\Event\DeviceRevokedEvent;
use Nowo\DeviceIntelligenceBundle\Event\DeviceTrustedEvent;
use Nowo\DeviceIntelligenceBundle\Trust\DeviceTrustService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DeviceTrustServiceTest extends TestCase
{
    public function testTrustAndRevokeDispatchEvents(): void
    {
        $now = new \DateTimeImmutable('2026-08-23T12:00:00Z');
        $devices = new InMemoryDeviceRepository();
        $users = new InMemoryDeviceUserRepository();
        $trusts = new InMemoryTrustedDeviceRepository();
        $engine = DeviceIntelligence::create($devices, new InMemoryObservationRepository(), $users, $trusts);
        $analysis = $engine->analyze(new AnalysisInput($now, SignalBag::empty(), '8.8.8.8'));
        $manager = new DeviceManager($devices, $users, $trusts, new FrozenClock($now));
        $dispatcher = new EventDispatcher();
        $events = [];
        $dispatcher->addListener(DeviceTrustedEvent::class, static function (DeviceTrustedEvent $e) use (&$events): void {
            $events[] = $e;
        });
        $dispatcher->addListener(DeviceRevokedEvent::class, static function (DeviceRevokedEvent $e) use (&$events): void {
            $events[] = $e;
        });
        $service = new DeviceTrustService($manager, $dispatcher);
        $user = new UserIdentifier('alice');

        $service->trust($analysis->device(), $user, null, 'laptop');
        self::assertTrue($service->isTrusted($analysis->device(), $user));
        $service->revoke($analysis->device(), $user);
        self::assertFalse($service->isTrusted($analysis->device(), $user));
        self::assertCount(2, $events);
    }
}
