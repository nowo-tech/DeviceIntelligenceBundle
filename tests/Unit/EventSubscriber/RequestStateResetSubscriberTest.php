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
use Nowo\DeviceIntelligence\Trust\TrustedDevice;
use Nowo\DeviceIntelligence\User\DeviceUserRelation;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligenceBundle\DependencyInjection\NowoDeviceIntelligenceExtension;
use Nowo\DeviceIntelligenceBundle\EventSubscriber\RequestStateResetSubscriber;
use Nowo\DeviceIntelligenceBundle\Profiler\DeviceIntelligenceDataCollector;
use Nowo\DeviceIntelligenceBundle\RateLimiter\SymfonyDeviceRateLimiter;
use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;
use Nowo\DeviceIntelligenceBundle\Tests\Support\ProcessedConfig;
use Nowo\DeviceIntelligenceBundle\Tests\Support\Scenario;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class RequestStateResetSubscriberTest extends TestCase
{
    public function testSubscribesEarlyOnRequest(): void
    {
        self::assertSame(
            [KernelEvents::REQUEST => ['onRequest', 4096]],
            RequestStateResetSubscriber::getSubscribedEvents(),
        );
    }

    public function testInMemoryStoresDoNotLeakIntoNextRequest(): void
    {
        $devices = new InMemoryDeviceRepository();
        $observations = new InMemoryObservationRepository();
        $users = new InMemoryDeviceUserRepository();
        $trusts = new InMemoryTrustedDeviceRepository();
        $subscriber = new RequestStateResetSubscriber([$devices, $observations, $users, $trusts, new \stdClass()]);

        $now = Scenario::now();
        $device = Scenario::device($now);
        $alice = new UserIdentifier('alice');

        // Request 1 (visitor alice).
        $subscriber->onRequest($this->event());
        $devices->save($device);
        $observation = Scenario::observation($device, null, $now);
        $observations->save($observation);
        $users->save(new DeviceUserRelation($device->id, $alice, $now, $now, 1));
        $trusts->save(new TrustedDevice($device->id, $alice, $now, null, null, 'laptop'));
        self::assertCount(1, $devices->all());

        // A fragment sub-request must not wipe the current request's data.
        $subscriber->onRequest($this->event(HttpKernelInterface::SUB_REQUEST));
        self::assertNotNull($trusts->findActive($device->id, $alice, $now));

        // Request 2 on the same worker, no services_resetter.
        $subscriber->onRequest($this->event());
        self::assertSame([], $devices->all());
        self::assertNull($devices->find($device->id));
        self::assertNull($observations->find($observation->id));
        self::assertSame([], $users->forDevice($device->id));
        self::assertNull($trusts->findActive($device->id, $alice, $now));
    }

    public function testRateLimiterFallbackIsRequestScoped(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willThrowException(new \RuntimeException('redis down'));
        $cache->method('set')->willThrowException(new \RuntimeException('redis down'));
        $limiter = new SymfonyDeviceRateLimiter(ProcessedConfig::object(), $cache);
        $subscriber = new RequestStateResetSubscriber([$limiter]);

        $subscriber->onRequest($this->event());
        self::assertTrue($limiter->consume('collect', 'ip', 'h1', null, null, 1, '1 minute'));
        self::assertFalse($limiter->consume('collect', 'ip', 'h1', null, null, 1, '1 minute'));

        $subscriber->onRequest($this->event());
        self::assertTrue($limiter->consume('collect', 'ip', 'h1', null, null, 1, '1 minute'));
    }

    public function testCollectorDoesNotShowPreviousRequestAnalysis(): void
    {
        $engine = DeviceIntelligence::create(
            new InMemoryDeviceRepository(),
            new InMemoryObservationRepository(),
            new InMemoryDeviceUserRepository(),
            new InMemoryTrustedDeviceRepository(),
        );
        $analysis = $engine->analyze(new AnalysisInput(new \DateTimeImmutable(), SignalBag::empty(), '1.1.1.1'));
        $collector = new DeviceIntelligenceDataCollector();
        $subscriber = new RequestStateResetSubscriber([$collector]);

        $subscriber->onRequest($this->event());
        $request = new Request();
        $request->attributes->set('_device', new DeviceContext($analysis));
        $collector->collect($request, new Response());
        self::assertTrue($collector->hasContext());

        $subscriber->onRequest($this->event());
        $collector->collect(new Request(), new Response());
        self::assertFalse($collector->hasContext());
    }

    public function testExtensionTagsResettableServices(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.secret', 's');
        $container->register('cache.app', ArrayAdapter::class);
        (new NowoDeviceIntelligenceExtension())->load([['doctrine' => ['enabled' => false]]], $container);

        foreach ([InMemoryDeviceRepository::class, InMemoryObservationRepository::class, InMemoryDeviceUserRepository::class, InMemoryTrustedDeviceRepository::class, SymfonyDeviceRateLimiter::class] as $id) {
            $definition = $container->getDefinition($id);
            self::assertTrue($definition->hasTag(RequestStateResetSubscriber::TAG), $id);
            self::assertTrue($definition->hasTag('kernel.reset'), $id);
        }
        self::assertTrue($container->hasDefinition(RequestStateResetSubscriber::class));
    }

    private function event(int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->createMock(HttpKernelInterface::class), new Request(), $type);
    }
}
