<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Nowo\DeviceIntelligenceBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\DeviceIntelligenceBundle\DependencyInjection\NowoDeviceIntelligenceExtension;
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

        $entityDir = __DIR__.'/Entity';
        if (is_dir($entityDir) && class_exists(DoctrineOrmMappingsPass::class)) {
            $container->addCompilerPass(DoctrineOrmMappingsPass::createAttributeMappingDriver(
                ['Nowo\\DeviceIntelligenceBundle\\Entity'],
                [$entityDir],
            ));
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
