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
 * Host app whose routing is the Symfony default `controllers: { resource: routing.controllers }`, optionally followed
 * by the documented import of the bundle routes.php (REQ-SF-004 rule 7).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class HostControllersRoutesTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly bool $importBundleRoutes)
    {
        // One environment per routing setup: each compiles its own container class (no reuse of a removed cache).
        parent::__construct($importBundleRoutes ? 'test_imported' : 'test_controllers', false);
    }

    public static function baseDir(): string
    {
        return sys_get_temp_dir().'/nowo-device-intelligence-host-controllers-kernel';
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
        return self::baseDir().'/'.($this->importBundleRoutes ? 'imported' : 'controllers-only').'/cache';
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
        $routes->import('routing.controllers');

        if ($this->importBundleRoutes) {
            $routes->import('@NowoDeviceIntelligenceBundle/Resources/config/routes.php');
        }
    }
}
