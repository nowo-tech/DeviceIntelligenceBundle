<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Integration\EntityManager;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\DeviceIntelligenceBundle\NowoDeviceIntelligenceBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Host kernel with a configurable Doctrine setup (1.7: `doctrine.entity_manager`, `doctrine.enabled`), with or
 * without DoctrineBundle. One cache directory per configuration and PHPUnit process.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class EntityManagerTestKernel extends Kernel
{
    use MicroKernelTrait;

    private readonly string $cacheKey;

    /**
     * @param array<string, mixed>|null $doctrine null: no DoctrineBundle at all
     * @param array<string, mixed>      $bundle
     */
    public function __construct(
        private readonly ?array $doctrine,
        private readonly array $bundle = [],
    ) {
        $this->cacheKey = hash('xxh128', serialize([$doctrine, $bundle]));
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        if (null !== $this->doctrine) {
            yield new DoctrineBundle();
        }
        yield new NowoDeviceIntelligenceBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 3);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/nowo-device-intelligence-em/'.getmypid().'/'.$this->cacheKey.'/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/nowo-device-intelligence-em/'.getmypid().'/'.$this->cacheKey.'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'router' => ['utf8' => true, 'resource' => 'kernel::loadRoutes', 'type' => 'service'],
        ]);
        if (null !== $this->doctrine) {
            $container->extension('doctrine', $this->doctrine);
        }
        $container->extension('nowo_device_intelligence', $this->bundle);
    }
}
