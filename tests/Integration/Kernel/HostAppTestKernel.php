<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Integration\Kernel;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\DeviceIntelligenceBundle\NowoDeviceIntelligenceBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Minimal host application: FrameworkBundle + DoctrineBundle with their default configuration and the bundle, no
 * test-only container tweaks (cache warmers, proxies and the framework prepend run as in a real host).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class HostAppTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct()
    {
        parent::__construct('test_host_app', false);
    }

    public static function baseDir(): string
    {
        return sys_get_temp_dir().'/nowo-device-intelligence-host-app-kernel';
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new NowoDeviceIntelligenceBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 3);
    }

    public function getCacheDir(): string
    {
        return self::baseDir().'/cache';
    }

    public function getLogDir(): string
    {
        return self::baseDir().'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'router' => ['utf8' => true, 'resource' => 'kernel::loadRoutes', 'type' => 'service'],
        ]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm' => [],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@NowoDeviceIntelligenceBundle/Resources/config/routes.php');
    }
}
