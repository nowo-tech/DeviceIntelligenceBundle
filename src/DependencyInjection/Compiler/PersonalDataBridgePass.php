<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\DependencyInjection\Compiler;

use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataEraserInterface;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataExporterInterface;
use Nowo\DeviceIntelligenceBundle\PersonalData\UserKit\DeviceIntelligenceUserKitEraser;
use Nowo\DeviceIntelligenceBundle\PersonalData\UserKit\DeviceIntelligenceUserKitExportProvider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Keeps the UserKit adapters only when their port is bound (1.9): with `doctrine.enabled: false` the bundle ships no
 * default eraser / exporter, so the adapters stay only if the host aliased the port interfaces to its own services.
 */
final readonly class PersonalDataBridgePass implements CompilerPassInterface
{
    public const ERASER_TAG = 'nowo_user_kit.personal_data.eraser';

    public const EXPORT_PROVIDER_TAG = 'nowo_user_kit.personal_data.export_provider';

    /**
     * Removes each adapter whose port has no service.
     */
    public function process(ContainerBuilder $container): void
    {
        $adapters = [
            DeviceIntelligenceUserKitEraser::class => DeviceIntelligencePersonalDataEraserInterface::class,
            DeviceIntelligenceUserKitExportProvider::class => DeviceIntelligencePersonalDataExporterInterface::class,
        ];
        foreach ($adapters as $adapter => $port) {
            if ($container->hasDefinition($adapter) && !$container->has($port)) {
                $container->removeDefinition($adapter);
            }
        }
    }
}
