<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\DeviceIntelligenceBundle\DependencyInjection\Compiler\DeviceIntelligenceEntityManagerPass;
use Nowo\DeviceIntelligenceBundle\DependencyInjection\NowoDeviceIntelligenceExtension;
use Nowo\DeviceIntelligenceBundle\Doctrine\ExcludedEntityNamespaceDriver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class DeviceIntelligenceEntityManagerPassTest extends TestCase
{
    public function testDoesNothingWithoutTheParameterOrWithTheDefaultManager(): void
    {
        $container = $this->container(null);
        (new DeviceIntelligenceEntityManagerPass())->process($container);
        self::assertCount(3, $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls());

        $container = $this->container(null);
        $container->getParameterBag()->remove(NowoDeviceIntelligenceExtension::ENTITY_MANAGER_PARAMETER);
        (new DeviceIntelligenceEntityManagerPass())->process($container);
        self::assertCount(3, $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls());
    }

    public function testRemovesTheBundleMappingFromTheOtherManagers(): void
    {
        $container = $this->container('device_intelligence');
        (new DeviceIntelligenceEntityManagerPass())->process($container);

        // Other managers: the host's driver for the namespace is gone; a driver that maps nothing comes first, before
        // the host's drivers and before any default driver (DoctrineBundle 3.4 auto_discover_entities).
        $defaultCalls = $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls();
        self::assertSame(
            [['addDriver', DeviceIntelligenceEntityManagerPass::ENTITY_NAMESPACE], ['addDriver', 'App\\Entity'], ['setDefaultDriver', null]],
            array_map(static fn (array $call): array => [$call[0], $call[1][1] ?? null], $defaultCalls),
        );
        self::assertInstanceOf(Definition::class, $defaultCalls[0][1][0]);
        self::assertSame(ExcludedEntityNamespaceDriver::class, $defaultCalls[0][1][0]->getClass());
        $bundleCalls = $container->getDefinition('doctrine.orm.device_intelligence_metadata_driver')->getMethodCalls();
        self::assertCount(1, $bundleCalls);
        self::assertEquals(new Reference('bundle'), $bundleCalls[0][1][0], 'the configured manager keeps its own driver');

        (new DeviceIntelligenceEntityManagerPass())->process($container);
        self::assertCount(1, $container->getDefinition('doctrine.orm.device_intelligence_metadata_driver')->getMethodCalls(), 'idempotent');
        self::assertCount(3, $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls(), 'idempotent');
    }

    public function testDisabledDoctrineRemovesTheMappingFromEveryManager(): void
    {
        $container = $this->container(null);
        $container->setParameter('nowo_device_intelligence.doctrine.enabled', false);
        (new DeviceIntelligenceEntityManagerPass())->process($container);

        // No manager maps the entities, not even through a default driver.
        $default = $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls();
        self::assertSame(
            [['addDriver', DeviceIntelligenceEntityManagerPass::ENTITY_NAMESPACE], ['addDriver', 'App\\Entity'], ['setDefaultDriver', null]],
            array_map(static fn (array $call): array => [$call[0], $call[1][1] ?? null], $default),
        );
        $bundle = $container->getDefinition('doctrine.orm.device_intelligence_metadata_driver')->getMethodCalls();
        self::assertCount(1, $bundle);
        self::assertInstanceOf(Definition::class, $bundle[0][1][0]);
        self::assertSame(ExcludedEntityNamespaceDriver::class, $bundle[0][1][0]->getClass());
    }

    public function testValidateOnlyChecksTheManagerAndKeepsTheChains(): void
    {
        $container = $this->container('device_intelligence');
        (new DeviceIntelligenceEntityManagerPass(true))->process($container);
        self::assertCount(3, $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls());

        $this->expectException(InvalidConfigurationException::class);
        (new DeviceIntelligenceEntityManagerPass(true))->process($this->container('missing'));
    }

    public function testFailsWhenTheManagerIsUnknown(): void
    {
        $container = $this->container('missing');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('"missing", but no such Doctrine entity manager');
        (new DeviceIntelligenceEntityManagerPass())->process($container);
    }

    public function testFailsWhenNoManagerIsConfiguredAtAll(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(NowoDeviceIntelligenceExtension::ENTITY_MANAGER_PARAMETER, 'device_intelligence');

        $this->expectException(InvalidConfigurationException::class);
        (new DeviceIntelligenceEntityManagerPass())->process($container);
    }

    public function testFailsWhenTheConfiguredManagerDoesNotMapTheEntities(): void
    {
        $container = $this->container('device_intelligence');
        $container->getDefinition('doctrine.orm.device_intelligence_metadata_driver')->setMethodCalls([]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('does not map');
        (new DeviceIntelligenceEntityManagerPass())->process($container);
    }

    private function container(?string $entityManager): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(NowoDeviceIntelligenceExtension::ENTITY_MANAGER_PARAMETER, $entityManager);
        $container->setParameter('doctrine.entity_managers', [
            'default' => 'doctrine.orm.default_entity_manager',
            'device_intelligence' => 'doctrine.orm.device_intelligence_entity_manager',
            'no_chain' => 'doctrine.orm.no_chain_entity_manager',
        ]);

        $container->setDefinition('doctrine.orm.default_metadata_driver', (new Definition())
            ->addMethodCall('addDriver', [new Reference('attribute'), 'App\\Entity'])
            ->addMethodCall('addDriver', [new Reference('attribute'), DeviceIntelligenceEntityManagerPass::ENTITY_NAMESPACE])
            ->addMethodCall('setDefaultDriver', [new Reference('auto_discover')]));
        $container->setDefinition('doctrine.orm.device_intelligence_metadata_driver', (new Definition())
            ->addMethodCall('addDriver', [new Reference('bundle'), DeviceIntelligenceEntityManagerPass::ENTITY_NAMESPACE]));

        return $container;
    }
}
