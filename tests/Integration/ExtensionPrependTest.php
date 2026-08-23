<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Integration;

use Nowo\DeviceIntelligence\DeviceIntelligence;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Port\DeviceRepositoryInterface;
use Nowo\DeviceIntelligenceBundle\DependencyInjection\Configuration;
use Nowo\DeviceIntelligenceBundle\DependencyInjection\NowoDeviceIntelligenceExtension;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineDeviceRepository;
use Nowo\DeviceIntelligenceBundle\Infrastructure\SystemClock;
use Nowo\DeviceIntelligenceBundle\NowoDeviceIntelligenceBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ExtensionPrependTest extends TestCase
{
    public function testPrependRegistersAssetPackageAndTwigPath(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'framework';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'twig';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });

        $extension = new NowoDeviceIntelligenceExtension();
        $extension->prepend($container);

        $framework = $container->getExtensionConfig('framework');
        self::assertSame(
            '/bundles/nowodeviceintelligence',
            $framework[0]['assets']['packages']['nowo_device_intelligence']['base_path'],
        );
        $twig = $container->getExtensionConfig('twig');
        self::assertContains('NowoDeviceIntelligenceBundle', $twig[0]['paths']);
    }

    public function testBundleExposesExtension(): void
    {
        $bundle = new NowoDeviceIntelligenceBundle();
        $ext    = $bundle->getContainerExtension();
        self::assertInstanceOf(NowoDeviceIntelligenceExtension::class, $ext);
        self::assertSame('nowo_device_intelligence', $ext->getAlias());
        $bundle->build(new ContainerBuilder());
        self::assertSame($ext, $bundle->getContainerExtension());
    }

    public function testLoadRegistersParametersAndInMemoryWhenDoctrineDisabled(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.secret', 'test-secret');
        $container->register('cache.app', ArrayAdapter::class);
        $extension = new NowoDeviceIntelligenceExtension();
        $extension->load([['doctrine' => ['enabled' => false], 'profiler' => false]], $container);

        self::assertTrue($container->getParameter('nowo_device_intelligence.enabled'));
        self::assertTrue($container->has(DeviceIntelligence::class));
        self::assertTrue($container->has(InMemoryDeviceRepository::class));
    }

    public function testLoadDoctrineAliases(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.secret', 'test-secret');
        $container->register('cache.app', ArrayAdapter::class);
        $container->register('clock', SystemClock::class);
        $extension = new NowoDeviceIntelligenceExtension();
        $extension->load([[]], $container);

        self::assertTrue($container->has(DeviceRepositoryInterface::class));
        self::assertSame(
            DoctrineDeviceRepository::class,
            (string) $container->getAlias(DeviceRepositoryInterface::class),
        );
    }

    public function testConfigurationAlias(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);
        self::assertArrayHasKey('default', $config['profiles']);
    }
}
