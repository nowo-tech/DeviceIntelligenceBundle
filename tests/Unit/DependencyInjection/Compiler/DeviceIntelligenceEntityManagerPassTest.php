<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\DeviceIntelligenceBundle\DependencyInjection\Compiler\DeviceIntelligenceEntityManagerPass;
use Nowo\DeviceIntelligenceBundle\DependencyInjection\NowoDeviceIntelligenceExtension;
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
        self::assertCount(2, $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls());

        $container = $this->container(null);
        $container->getParameterBag()->remove(NowoDeviceIntelligenceExtension::ENTITY_MANAGER_PARAMETER);
        (new DeviceIntelligenceEntityManagerPass())->process($container);
        self::assertCount(2, $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls());
    }

    public function testRemovesTheBundleMappingFromTheOtherManagers(): void
    {
        $container = $this->container('device_intelligence');
        (new DeviceIntelligenceEntityManagerPass())->process($container);

        $defaultCalls = $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls();
        self::assertSame(['App\\Entity'], array_map(static fn (array $call): mixed => $call[1][1], $defaultCalls));
        self::assertCount(1, $container->getDefinition('doctrine.orm.device_intelligence_metadata_driver')->getMethodCalls());
    }

    public function testDisabledDoctrineRemovesTheMappingFromEveryManager(): void
    {
        $container = $this->container(null);
        $container->setParameter('nowo_device_intelligence.doctrine.enabled', false);
        (new DeviceIntelligenceEntityManagerPass())->process($container);

        $default = $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls();
        self::assertSame(['App\\Entity'], array_map(static fn (array $call): mixed => $call[1][1], $default));
        self::assertSame([], $container->getDefinition('doctrine.orm.device_intelligence_metadata_driver')->getMethodCalls());
    }

    public function testValidateOnlyChecksTheManagerAndKeepsTheChains(): void
    {
        $container = $this->container('device_intelligence');
        (new DeviceIntelligenceEntityManagerPass(true))->process($container);
        self::assertCount(2, $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls());

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
            ->addMethodCall('addDriver', [new Reference('attribute'), DeviceIntelligenceEntityManagerPass::ENTITY_NAMESPACE]));
        $container->setDefinition('doctrine.orm.device_intelligence_metadata_driver', (new Definition())
            ->addMethodCall('addDriver', [new Reference('bundle'), DeviceIntelligenceEntityManagerPass::ENTITY_NAMESPACE]));

        return $container;
    }
}
