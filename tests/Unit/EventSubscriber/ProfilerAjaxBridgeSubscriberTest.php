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
use Nowo\DeviceIntelligenceBundle\EventSubscriber\ProfilerAjaxBridgeSubscriber;
use Nowo\DeviceIntelligenceBundle\Profiler\DeviceIntelligenceDataCollector;
use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\HttpKernel\Profiler\ProfilerStorageInterface;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ProfilerAjaxBridgeSubscriberTest extends TestCase
{
    public function testCopiesCollectAnalysisOntoTheHtmlProfile(): void
    {
        $engine = DeviceIntelligence::create(
            new InMemoryDeviceRepository(),
            new InMemoryObservationRepository(),
            new InMemoryDeviceUserRepository(),
            new InMemoryTrustedDeviceRepository(),
        );
        $analysis = $engine->analyze(new AnalysisInput(new \DateTimeImmutable(), SignalBag::empty(), '1.1.1.1'));
        $parentCollector = new DeviceIntelligenceDataCollector();
        $parentCollector->collect(Request::create('/es'), new Response());
        self::assertFalse($parentCollector->hasContext());

        $parent = new Profile('f9297c');
        $parent->addCollector($parentCollector);

        $profiler = $this->createMock(Profiler::class);
        $profiler->expects(self::once())->method('loadProfile')->with('f9297c')->willReturn($parent);
        $storage = $this->createMock(ProfilerStorageInterface::class);
        $storage->expects(self::once())->method('write')->with($parent);

        $request = Request::create('/_device/collect', 'POST');
        $request->attributes->set('_route', 'nowo_device_intelligence_collect');
        $request->attributes->set('_device', new DeviceContext($analysis));
        $request->headers->set('X-Previous-Debug-Token', 'f9297c');

        $subscriber = new ProfilerAjaxBridgeSubscriber($profiler, $storage);
        $subscriber->onResponse(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        ));

        self::assertTrue($parentCollector->hasContext());
        self::assertTrue($parentCollector->isFromAjax());
        self::assertSame($analysis->device()->id->value, $parentCollector->getDeviceId());
        self::assertSame(
            [KernelEvents::RESPONSE => ['onResponse', -2048]],
            ProfilerAjaxBridgeSubscriber::getSubscribedEvents(),
        );
    }

    public function testSkipsWhenProfilerOrHeaderMissing(): void
    {
        $subscriber = new ProfilerAjaxBridgeSubscriber(null);
        $request = Request::create('/_device/collect', 'POST');
        $request->attributes->set('_route', 'nowo_device_intelligence_collect');
        $subscriber->onResponse(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        ));

        $profiler = $this->createMock(Profiler::class);
        $profiler->expects(self::never())->method('loadProfile');
        $withHeader = new ProfilerAjaxBridgeSubscriber($profiler);
        $withHeader->onResponse(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/es'),
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        ));
        $this->addToAssertionCount(1);
    }
}
