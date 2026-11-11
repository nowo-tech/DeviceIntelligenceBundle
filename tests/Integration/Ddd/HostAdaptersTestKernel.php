<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Integration\Ddd;

use Nowo\DeviceIntelligence\Port\DeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Port\DeviceUserRepositoryInterface;
use Nowo\DeviceIntelligence\Port\ObservationRepositoryInterface;
use Nowo\DeviceIntelligence\Port\TrustedDeviceRepositoryInterface;
use Nowo\DeviceIntelligenceBundle\NowoDeviceIntelligenceBundle;
use Nowo\DeviceIntelligenceBundle\Tests\Support\Host\HostAnalyzeController;
use Nowo\DeviceIntelligenceBundle\Tests\Support\Host\HostDeviceStore;
use Nowo\DeviceIntelligenceBundle\Tests\Support\Host\HostDeviceUserStore;
use Nowo\DeviceIntelligenceBundle\Tests\Support\Host\HostObservationStore;
use Nowo\DeviceIntelligenceBundle\Tests\Support\Host\HostTrustStore;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * A DDD host without Doctrine (no DoctrineBundle registered): FrameworkBundle and the bundle, the core repository ports
 * aliased to the host's own stores, and a host controller that runs the analysis.
 */
final class HostAdaptersTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $bundle `nowo_device_intelligence` configuration
     */
    public function __construct(private readonly array $bundle)
    {
        parent::__construct('test_ddd', false);
    }

    public static function baseDir(): string
    {
        return sys_get_temp_dir().'/nowo-device-intelligence-ddd/'.getmypid();
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new NowoDeviceIntelligenceBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 3);
    }

    public function getCacheDir(): string
    {
        return self::baseDir().'/'.hash('xxh128', serialize($this->bundle)).'/cache';
    }

    public function getLogDir(): string
    {
        return self::baseDir().'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false, 'router' => ['utf8' => true]]);
        $container->extension('nowo_device_intelligence', $this->bundle);

        $services = $container->services();
        $services->set(HostDeviceStore::class)->public();
        $services->set(HostObservationStore::class)->public();
        $services->set(HostDeviceUserStore::class)->public();
        $services->set(HostTrustStore::class)->public();
        // The host binds the core ports to its own adapters (its aliases win over the bundle's defaults).
        $services->alias(DeviceRepositoryInterface::class, HostDeviceStore::class)->public();
        $services->alias(ObservationRepositoryInterface::class, HostObservationStore::class)->public();
        $services->alias(DeviceUserRepositoryInterface::class, HostDeviceUserStore::class)->public();
        $services->alias(TrustedDeviceRepositoryInterface::class, HostTrustStore::class)->public();
        $services->set(HostAnalyzeController::class)->args([service('Nowo\DeviceIntelligence\DeviceIntelligenceInterface')])->public()->tag('controller.service_arguments');
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('host_analyze', '/analyze/{user}')->controller(HostAnalyzeController::class);
    }
}
