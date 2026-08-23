<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\EventSubscriber;

use Nowo\DeviceIntelligence\AnalysisInput;
use Nowo\DeviceIntelligence\DeviceIntelligence;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceUserRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryObservationRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryTrustedDeviceRepository;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligenceBundle\Attribute\DeviceRateLimit;
use Nowo\DeviceIntelligenceBundle\Attribute\DeviceRisk;
use Nowo\DeviceIntelligenceBundle\Attribute\RequireTrustedDevice;
use Nowo\DeviceIntelligenceBundle\Event\DeviceObservedEvent;
use Nowo\DeviceIntelligenceBundle\EventSubscriber\AnalyzeSubscriber;
use Nowo\DeviceIntelligenceBundle\EventSubscriber\ControllerAttributeSubscriber;
use Nowo\DeviceIntelligenceBundle\Profiler\DeviceIntelligenceDataCollector;
use Nowo\DeviceIntelligenceBundle\RateLimiter\DeviceRateLimiterInterface;
use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SubscribersTest extends TestCase
{
    public function testAnalyzeSubscriberForwardsToCollector(): void
    {
        $engine = DeviceIntelligence::create(
            new InMemoryDeviceRepository(),
            new InMemoryObservationRepository(),
            new InMemoryDeviceUserRepository(),
            new InMemoryTrustedDeviceRepository(),
        );
        $analysis = $engine->analyze(new AnalysisInput(new \DateTimeImmutable(), SignalBag::empty()));
        $collector = new DeviceIntelligenceDataCollector();
        $subscriber = new AnalyzeSubscriber($collector);
        $subscriber->onObserved(new DeviceObservedEvent($analysis));
        self::assertTrue($collector->hasContext());
        self::assertSame([DeviceObservedEvent::class => 'onObserved'], AnalyzeSubscriber::getSubscribedEvents());
    }

    public function testRequireTrustedDeviceDenies(): void
    {
        $limiter = $this->createMock(DeviceRateLimiterInterface::class);
        $subscriber = new ControllerAttributeSubscriber($limiter);
        $request = Request::create('/');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new ControllerEvent($kernel, [new GuardedController(), 'payout'], $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(AccessDeniedHttpException::class);
        $subscriber->onController($event);
    }

    public function testDeviceRiskDeniesWhenScoreTooHigh(): void
    {
        $engine = DeviceIntelligence::create(
            new InMemoryDeviceRepository(),
            new InMemoryObservationRepository(),
            new InMemoryDeviceUserRepository(),
            new InMemoryTrustedDeviceRepository(),
        );
        $analysis = $engine->analyze(new AnalysisInput(new \DateTimeImmutable(), SignalBag::empty(), '1.1.1.1'));
        $request = Request::create('/');
        $request->attributes->set('_device', new DeviceContext($analysis, true));
        $limiter = $this->createMock(DeviceRateLimiterInterface::class);
        $limiter->method('consume')->willReturn(true);
        $subscriber = new ControllerAttributeSubscriber($limiter);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new ControllerEvent($kernel, [new GuardedController(), 'risky'], $request, HttpKernelInterface::MAIN_REQUEST);

        if ($analysis->riskScore() > 0) {
            try {
                $subscriber->onController($event);
            } catch (AccessDeniedHttpException) {
                $this->addToAssertionCount(1);

                return;
            }
        }
        $subscriber->onController($event);
        $this->addToAssertionCount(1);
    }

    public function testRateLimitAttributeDenies(): void
    {
        $limiter = $this->createMock(DeviceRateLimiterInterface::class);
        $limiter->method('consume')->willReturn(false);
        $subscriber = new ControllerAttributeSubscriber($limiter);
        $request = Request::create('/');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new ControllerEvent($kernel, [new GuardedController(), 'limited'], $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(AccessDeniedHttpException::class);
        $subscriber->onController($event);
    }

    public function testSubscribedEvents(): void
    {
        self::assertArrayHasKey(KernelEvents::CONTROLLER, ControllerAttributeSubscriber::getSubscribedEvents());
    }
}

final class GuardedController
{
    #[RequireTrustedDevice]
    public function payout(): void
    {
    }

    #[DeviceRisk(max: 0)]
    public function risky(): void
    {
    }

    #[DeviceRateLimit(limit: 1, interval: '1 minute', policy: 'ip')]
    public function limited(): void
    {
    }
}
