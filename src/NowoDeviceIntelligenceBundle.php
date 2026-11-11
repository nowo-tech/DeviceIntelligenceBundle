<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Nowo\DeviceIntelligenceBundle\DependencyInjection\Compiler\DeviceIntelligenceEntityManagerPass;
use Nowo\DeviceIntelligenceBundle\DependencyInjection\Compiler\PersonalDataBridgePass;
use Nowo\DeviceIntelligenceBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\DeviceIntelligenceBundle\DependencyInjection\NowoDeviceIntelligenceExtension;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bridge for the framework-agnostic Device Intelligence core.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class NowoDeviceIntelligenceBundle extends Bundle
{
    /**
     * Registers the bundle compiler passes.
     */
    public function build(ContainerBuilder $container): void
    {
        // REQ-TWIG-001: templates/bundles/NowoDeviceIntelligenceBundle/ first, then the bundle views.
        $container->addCompilerPass(new TwigPathsPass());
        // 1.9: before UserKit collects the tagged erasers / export providers (TYPE_BEFORE_OPTIMIZATION, priority 0).
        $container->addCompilerPass(new PersonalDataBridgePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 20);

        $entityDir = __DIR__.'/Entity';
        if (is_dir($entityDir) && class_exists(DoctrineOrmMappingsPass::class)) {
            // Before the mapping pass: an unknown doctrine.entity_manager fails with a clear message.
            $container->addCompilerPass(new DeviceIntelligenceEntityManagerPass(true), PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);
            // 1.7: mapped only with doctrine.enabled (rule 8), on doctrine.entity_manager when set (rule 6).
            $container->addCompilerPass(DoctrineOrmMappingsPass::createAttributeMappingDriver(
                ['Nowo\\DeviceIntelligenceBundle\\Entity'],
                [$entityDir],
                [NowoDeviceIntelligenceExtension::ENTITY_MANAGER_PARAMETER],
                NowoDeviceIntelligenceExtension::MAPPING_ENABLED_PARAMETER,
            ));
            // After the mapping pass (and DoctrineBundle's auto_mapping): keep the entities on one manager.
            $container->addCompilerPass(new DeviceIntelligenceEntityManagerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -10);
        }
    }

    /**
     * Returns the container extension.
     */
    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            // @igor-ignore - Symfony bundle extension lazy-init at boot; not request state
            $this->extension = new NowoDeviceIntelligenceExtension();
        }

        return $this->extension;
    }
}
