<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\EventSubscriber;

use Nowo\DeviceIntelligence\AnalysisInput;
use Nowo\DeviceIntelligence\Device\DeviceManager;
use Nowo\DeviceIntelligence\DeviceIntelligence;
use Nowo\DeviceIntelligence\Infrastructure\FrozenClock;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceUserRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryObservationRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryTrustedDeviceRepository;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligence\Velocity\InMemoryVelocityEngine;
use Nowo\DeviceIntelligenceBundle\EventSubscriber\DeviceRequestSubscriber;
use Nowo\DeviceIntelligenceBundle\EventSubscriber\SecurityDeviceSubscriber;
use Nowo\DeviceIntelligenceBundle\Http\ObservationTokenIssuer;
use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;
use Nowo\DeviceIntelligenceBundle\Request\TokenDeviceContextFactory;
use Nowo\DeviceIntelligenceBundle\Tests\Support\ProcessedConfig;
use Nowo\DeviceIntelligenceBundle\User\SecurityUserIdentifierResolver;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class RequestAndSecuritySubscriberTest extends TestCase
{
    public function testDeviceRequestSubscriberSkipsWhenDisabled(): void
    {
        $config = ProcessedConfig::object(['enabled' => false]);
        $now = new \DateTimeImmutable();
        $devices = new InMemoryDeviceRepository();
        $observations = new InMemoryObservationRepository();
        $manager = new DeviceManager(
            $devices,
            new InMemoryDeviceUserRepository(),
            new InMemoryTrustedDeviceRepository(),
            new FrozenClock($now),
        );
        $tokens = new ObservationTokenIssuer($config, new FrozenClock($now), 's');
        $subscriber = new DeviceRequestSubscriber(
            $config,
            $tokens,
            $observations,
            $manager,
            new TokenDeviceContextFactory(),
        );
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onRequest($event);
        self::assertNull($event->getRequest()->attributes->get('_device'));
        self::assertArrayHasKey(KernelEvents::REQUEST, DeviceRequestSubscriber::getSubscribedEvents());
    }

    public function testSecurityLogoutAndFailureWithoutContext(): void
    {
        $now = new \DateTimeImmutable();
        $devices = new InMemoryDeviceRepository();
        $users = new InMemoryDeviceUserRepository();
        $trusts = new InMemoryTrustedDeviceRepository();
        $manager = new DeviceManager($devices, $users, $trusts, new FrozenClock($now));
        $stack = new RequestStack();
        $stack->push(Request::create('/'));
        $subscriber = new SecurityDeviceSubscriber(
            $manager,
            new SecurityUserIdentifierResolver(),
            new InMemoryVelocityEngine(),
            $stack,
            new NullLogger(),
        );
        $subscriber->onLogout($this->createMock(LogoutEvent::class));
        $subscriber->onLoginFailure($this->createMock(LoginFailureEvent::class));
        self::assertNotSame([], SecurityDeviceSubscriber::getSubscribedEvents());

        $engine = DeviceIntelligence::create($devices, new InMemoryObservationRepository(), $users, $trusts);
        $analysis = $engine->analyze(new AnalysisInput($now, SignalBag::empty(), '1.2.3.4'));
        $request = Request::create('/');
        $request->attributes->set('_device', new DeviceContext($analysis));
        $stack->pop();
        $stack->push($request);
        $subscriber->onLoginFailure($this->createMock(LoginFailureEvent::class));
        $this->addToAssertionCount(1);
    }
}
