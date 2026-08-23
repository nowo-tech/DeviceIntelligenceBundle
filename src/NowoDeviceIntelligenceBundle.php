<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
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
    public function build(ContainerBuilder $container): void
    {
        $entityDir = __DIR__ . '/Entity';
        if (is_dir($entityDir) && class_exists(DoctrineOrmMappingsPass::class)) {
            $container->addCompilerPass(DoctrineOrmMappingsPass::createAttributeMappingDriver(
                ['Nowo\\DeviceIntelligenceBundle\\Entity'],
                [$entityDir],
            ));
        }
    }

    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new NowoDeviceIntelligenceExtension();
        }

        return $this->extension;
    }
}
