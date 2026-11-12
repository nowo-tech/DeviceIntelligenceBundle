<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Integration\Ddd;

use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Port\DeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Port\DeviceUserRepositoryInterface;
use Nowo\DeviceIntelligence\Port\ObservationRepositoryInterface;
use Nowo\DeviceIntelligence\Port\TrustedDeviceRepositoryInterface;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligenceBundle\Tests\Support\Host\HostDeviceStore;
use Nowo\DeviceIntelligenceBundle\Tests\Support\Host\HostDeviceUserStore;
use Nowo\DeviceIntelligenceBundle\Tests\Support\Host\HostObservationStore;
use Nowo\DeviceIntelligenceBundle\Tests\Support\Host\HostTrustStore;
use Nowo\DeviceIntelligenceBundle\Trust\DeviceTrustServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernel;

/**
 * 1.8 (architecture bar rule 8, both host kinds): a DDD host without Doctrine — no DoctrineBundle, the repository ports
 * bound to its own stores — runs the analysis and the trust API on its adapters only, in a kept-alive kernel
 * (FrankenPHP worker mode, kernel reset off: `http_kernel` handle / terminate, no services resetter) serving two users
 * without mixing their devices, with flat memory. The standard host (DoctrineBundle, attribute entities) is covered by
 * `DoctrinePersistenceKernelTest` and `HostAppKernelTest`.
 */
final class HostAdaptersKernelTest extends TestCase
{
    private ?HostAdaptersTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        (new Filesystem())->remove(HostAdaptersTestKernel::baseDir());
    }

    public function testWithoutDoctrineTheHostAdaptersStoreEverything(): void
    {
        $container = $this->boot(['doctrine' => ['enabled' => false]]);

        self::assertFalse($container->has('doctrine'));
        self::assertInstanceOf(HostDeviceStore::class, $container->get(DeviceRepositoryInterface::class));
        self::assertInstanceOf(HostObservationStore::class, $container->get(ObservationRepositoryInterface::class));
        self::assertInstanceOf(HostDeviceUserStore::class, $container->get(DeviceUserRepositoryInterface::class));
        $trustStore = $container->get(TrustedDeviceRepositoryInterface::class);
        self::assertInstanceOf(HostTrustStore::class, $trustStore);

        $devices = $container->get(HostDeviceStore::class);
        self::assertInstanceOf(HostDeviceStore::class, $devices);
        $device = $devices->find(new DeviceId($this->get('alice')['device']));
        self::assertNotNull($device);
        self::assertGreaterThan(0, $devices->writes);
        $trust = $container->get(DeviceTrustServiceInterface::class);
        self::assertInstanceOf(DeviceTrustServiceInterface::class, $trust);
        $trust->trust($device, new UserIdentifier('alice'));
        self::assertSame(1, $trustStore->writes);
        self::assertTrue($trust->isTrusted($device, new UserIdentifier('alice')));
        self::assertFalse($trust->isTrusted($device, new UserIdentifier('bob')));
    }

    public function testKeptAliveKernelServesTwoUsersOnTheHostStores(): void
    {
        $container = $this->boot(['doctrine' => ['enabled' => false]]);
        $devices = $container->get(HostDeviceStore::class);
        self::assertInstanceOf(HostDeviceStore::class, $devices);
        $seen = [];
        $before = 0;

        for ($i = 0; $i < 1200; ++$i) {
            $user = 0 === $i % 2 ? 'alice' : 'bob';
            $result = $this->get($user);
            $seen[$user] ??= $result['device'];
            self::assertSame($seen[$user], $result['device'], 'the host store keeps the device between requests');
            self::assertSame(intdiv($i, 2) + 1, $result['observations'], $user.' #'.$i);

            if (199 === $i) {
                gc_collect_cycles();
                $before = memory_get_usage();
            }
        }
        gc_collect_cycles();
        self::assertLessThan(4 * 1024 * 1024, memory_get_usage() - $before, 'Memory must stay flat after warm-up.');

        self::assertNotSame($seen['alice'], $seen['bob']);
        self::assertCount(2, $devices->all());
        self::assertSame(1200, $devices->writes, 'every analysis wrote through the host adapter');
    }

    public function testWithoutDoctrineTheDefaultConfigurationFailsClearly(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('set nowo_device_intelligence.doctrine.enabled: false and bind the core repository ports');

        $this->boot([]);
    }

    /**
     * @param array<string, mixed> $bundle
     */
    private function boot(array $bundle): ContainerInterface
    {
        $this->kernel = new HostAdaptersTestKernel($bundle);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    /**
     * One request through `http_kernel` (no services resetter in between).
     *
     * @return array{device: string, observations: int}
     */
    private function get(string $user): array
    {
        self::assertNotNull($this->kernel);
        $http = $this->kernel->getContainer()->get('http_kernel');
        self::assertInstanceOf(HttpKernel::class, $http);
        $request = Request::create('/analyze/'.$user);
        $response = $http->handle($request);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $http->terminate($request, $response);
        /** @var array{device: string, observations: int} $data */
        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $data;
    }
}
