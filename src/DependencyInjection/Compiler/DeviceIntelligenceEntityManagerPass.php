<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\DependencyInjection\Compiler;

use Nowo\DeviceIntelligenceBundle\DependencyInjection\NowoDeviceIntelligenceExtension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Keeps the bundle entities on `doctrine.entity_manager` only (1.7, architecture bar rule 6).
 *
 * DoctrineBundle's `auto_mapping` (the Flex default on the default manager) maps every bundle's `Entity` directory,
 * so with a dedicated manager configured the entities would be mapped twice and `getManagerForClass()` would return
 * the default manager. This pass removes the bundle namespace from the mapping driver chain of every other manager
 * and fails the build when the configured manager does not exist or does not map the entities.
 */
final readonly class DeviceIntelligenceEntityManagerPass implements CompilerPassInterface
{
    /**
     * @param bool $validateOnly true: only fail clearly on an unknown manager (registered before the mapping pass,
     *                           which would otherwise fail with a missing `doctrine.orm.<name>_metadata_driver`)
     */
    public function __construct(
        private bool $validateOnly = false,
    ) {
    }

    public const ENTITY_NAMESPACE = 'Nowo\\DeviceIntelligenceBundle\\Entity';

    /**
     * Removes the bundle mapping from the managers other than `doctrine.entity_manager`.
     */
    public function process(ContainerBuilder $container): void
    {
        $managers = $container->hasParameter('doctrine.entity_managers') ? $container->getParameter('doctrine.entity_managers') : [];
        $managers = \is_array($managers) ? $managers : [];

        // doctrine.enabled: false (rule 8): no manager maps the entities, not even through auto_mapping.
        if (!$this->validateOnly && $container->hasParameter('nowo_device_intelligence.doctrine.enabled') && false === $container->getParameter('nowo_device_intelligence.doctrine.enabled')) {
            $this->keepOn($container, array_keys($managers), null);

            return;
        }

        // 1.8 (rule 8): a host without Doctrine ORM gets a clear message instead of the mapping pass's "Could not find
        // the manager name parameter".
        if ($this->validateOnly && $container->hasParameter(NowoDeviceIntelligenceExtension::MAPPING_ENABLED_PARAMETER) && !$container->hasParameter('doctrine.entity_managers')) {
            throw new InvalidConfigurationException('nowo_device_intelligence.doctrine.enabled is true (the default), but Doctrine ORM is not configured (DoctrineBundle with doctrine.orm). Configure it, or set nowo_device_intelligence.doctrine.enabled: false and bind the core repository ports (DeviceRepositoryInterface, ObservationRepositoryInterface, DeviceUserRepositoryInterface, TrustedDeviceRepositoryInterface) to your own adapters.');
        }

        if (!$container->hasParameter(NowoDeviceIntelligenceExtension::ENTITY_MANAGER_PARAMETER)) {
            return;
        }

        $name = $container->getParameter(NowoDeviceIntelligenceExtension::ENTITY_MANAGER_PARAMETER);
        if (!\is_string($name) || '' === $name) {
            return;
        }

        if (!isset($managers[$name])) {
            throw new InvalidConfigurationException(\sprintf('nowo_device_intelligence.doctrine.entity_manager is "%s", but no such Doctrine entity manager is configured (known: "%s"). Add it under doctrine.orm.entity_managers or remove the option.', $name, implode('", "', array_keys($managers))));
        }

        if ($this->validateOnly) {
            return;
        }

        $this->keepOn($container, array_keys($managers), $name);
    }

    /**
     * Keeps the bundle drivers on manager $name only (null: on none).
     *
     * @param list<int|string> $managers
     */
    private function keepOn(ContainerBuilder $container, array $managers, ?string $name): void
    {
        foreach ($managers as $manager) {
            $chainId = \sprintf('doctrine.orm.%s_metadata_driver', $manager);
            if (!$container->hasDefinition($chainId)) {
                continue;
            }

            $chain = $container->getDefinition($chainId);
            $calls = [];
            $maps = false;
            foreach ($chain->getMethodCalls() as $call) {
                $isBundleDriver = 'addDriver' === $call[0] && \is_string($call[1][1] ?? null) && str_starts_with($call[1][1], self::ENTITY_NAMESPACE);
                if ($isBundleDriver && $manager !== $name) {
                    continue;
                }

                $maps = $maps || $isBundleDriver;
                $calls[] = $call;
            }

            $chain->setMethodCalls($calls);

            if ($manager === $name && !$maps) {
                throw new InvalidConfigurationException(\sprintf('The Doctrine entity manager "%s" (nowo_device_intelligence.doctrine.entity_manager) does not map %s.', $name, self::ENTITY_NAMESPACE));
            }
        }
    }
}
