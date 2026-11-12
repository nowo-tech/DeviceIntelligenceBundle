<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Integration\EntityManager;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Nowo\DeviceIntelligenceBundle\NowoDeviceIntelligenceBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
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
     * @param array<string, mixed>|null $doctrine          null: no DoctrineBundle at all
     * @param array<string, mixed>      $bundle
     * @param bool                      $hostDefaultDriver the host gives its default manager's chain a catch-all
     *                                                     default driver (what DoctrineBundle 3.4
     *                                                     `auto_discover_entities` does)
     */
    public function __construct(
        private readonly ?array $doctrine,
        private readonly array $bundle = [],
        private readonly bool $hostDefaultDriver = false,
    ) {
        $this->cacheKey = hash('xxh128', serialize([$doctrine, $bundle, $hostDefaultDriver]));
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

    protected function build(ContainerBuilder $container): void
    {
        if (!$this->hostDefaultDriver) {
            return;
        }

        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                // An attribute driver answers "not transient" for any class carrying #[ORM\Entity], whatever its paths.
                $container->getDefinition('doctrine.orm.default_metadata_driver')
                    ->addMethodCall('setDefaultDriver', [new Definition(AttributeDriver::class, [[__DIR__.'/Entity'], true])]);
            }
        });
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
