<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\DependencyInjection;

use Nowo\DeviceIntelligenceBundle\DependencyInjection\Compiler\PersonalDataBridgePass;
use Nowo\DeviceIntelligenceBundle\DependencyInjection\Configuration;
use Nowo\DeviceIntelligenceBundle\DependencyInjection\NowoDeviceIntelligenceExtension;
use Nowo\DeviceIntelligenceBundle\NowoDeviceIntelligenceBundle;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataEraser;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataEraserInterface;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataExporter;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataExporterInterface;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataSubjectResolverInterface;
use Nowo\DeviceIntelligenceBundle\PersonalData\UserIdentifierSubjectResolver;
use Nowo\DeviceIntelligenceBundle\PersonalData\UserKit\DeviceIntelligenceUserKitEraser;
use Nowo\DeviceIntelligenceBundle\PersonalData\UserKit\DeviceIntelligenceUserKitExportProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * GDPR ports wiring (1.9): Doctrine defaults, the subject resolver, the UserKit adapters (UserKit is a dev dependency)
 * with their tags and priorities, `personal_data.enabled: false`, and a host without Doctrine.
 */
final class PersonalDataWiringTest extends TestCase
{
    public function testStandardHostGetsThePortsAndTheUserKitAdapters(): void
    {
        $container = $this->load([]);

        self::assertSame(DeviceIntelligencePersonalDataEraser::class, (string) $container->getAlias(DeviceIntelligencePersonalDataEraserInterface::class));
        self::assertSame(DeviceIntelligencePersonalDataExporter::class, (string) $container->getAlias(DeviceIntelligencePersonalDataExporterInterface::class));
        self::assertTrue($container->getAlias(DeviceIntelligencePersonalDataEraserInterface::class)->isPublic());
        self::assertSame(UserIdentifierSubjectResolver::class, (string) $container->getAlias(DeviceIntelligencePersonalDataSubjectResolverInterface::class));
        self::assertTrue($container->getParameter('nowo_device_intelligence.personal_data.enabled'));

        self::assertSame([['priority' => DeviceIntelligenceUserKitEraser::PRIORITY]], $container->getDefinition(DeviceIntelligenceUserKitEraser::class)->getTag('nowo_user_kit.personal_data.eraser'));
        self::assertSame([['priority' => DeviceIntelligenceUserKitExportProvider::PRIORITY]], $container->getDefinition(DeviceIntelligenceUserKitExportProvider::class)->getTag('nowo_user_kit.personal_data.export_provider'));

        (new PersonalDataBridgePass())->process($container);
        self::assertTrue($container->hasDefinition(DeviceIntelligenceUserKitEraser::class), 'its port is bound');
        self::assertTrue($container->hasDefinition(DeviceIntelligenceUserKitExportProvider::class));
    }

    public function testDisabledBridgeKeepsThePortsOnly(): void
    {
        $container = $this->load(['personal_data' => ['enabled' => false, 'subject_resolver' => 'app.subjects']]);

        self::assertTrue($container->hasAlias(DeviceIntelligencePersonalDataEraserInterface::class));
        self::assertFalse($container->hasDefinition(DeviceIntelligenceUserKitEraser::class));
        self::assertFalse($container->hasDefinition(DeviceIntelligenceUserKitExportProvider::class));
        self::assertSame('app.subjects', (string) $container->getAlias(DeviceIntelligencePersonalDataSubjectResolverInterface::class));
    }

    public function testWithoutDoctrineTheAdaptersNeedTheHostsPorts(): void
    {
        $container = $this->load(['doctrine' => ['enabled' => false]]);
        self::assertFalse($container->has(DeviceIntelligencePersonalDataEraserInterface::class));
        (new PersonalDataBridgePass())->process($container);
        self::assertFalse($container->hasDefinition(DeviceIntelligenceUserKitEraser::class));
        self::assertFalse($container->hasDefinition(DeviceIntelligenceUserKitExportProvider::class));

        $host = $this->load(['doctrine' => ['enabled' => false]]);
        $host->register('app.device_eraser', DeviceIntelligencePersonalDataEraser::class);
        $host->setAlias(DeviceIntelligencePersonalDataEraserInterface::class, 'app.device_eraser');
        (new PersonalDataBridgePass())->process($host);
        self::assertTrue($host->hasDefinition(DeviceIntelligenceUserKitEraser::class), 'the host bound the eraser port');
        self::assertFalse($host->hasDefinition(DeviceIntelligenceUserKitExportProvider::class));
    }

    public function testTheBundleRegistersTheBridgePass(): void
    {
        $container = new ContainerBuilder();
        (new NowoDeviceIntelligenceBundle())->build($container);

        $passes = array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof PersonalDataBridgePass);
        self::assertCount(1, $passes);
    }

    public function testSubjectResolverMustBeAServiceId(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('personal_data.subject_resolver');
        (new Processor())->processConfiguration(new Configuration(), [['personal_data' => ['subject_resolver' => '']]]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.secret', 's');
        $container->setParameter('kernel.environment', 'test');
        (new NowoDeviceIntelligenceExtension())->load([$config], $container);

        return $container;
    }
}
