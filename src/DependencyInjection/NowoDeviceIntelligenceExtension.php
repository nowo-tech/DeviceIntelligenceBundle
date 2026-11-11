<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\DependencyInjection;

use Nowo\DeviceIntelligence\Device\DefaultDeviceLabeler;
use Nowo\DeviceIntelligence\Device\DeviceLabelerInterface;
use Nowo\DeviceIntelligence\Device\DeviceManager;
use Nowo\DeviceIntelligence\DeviceIntelligence;
use Nowo\DeviceIntelligence\DeviceIntelligenceInterface;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceUserRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryObservationRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryTrustedDeviceRepository;
use Nowo\DeviceIntelligence\Matching\Candidate\DeviceCandidateProviderInterface;
use Nowo\DeviceIntelligence\Matching\Candidate\RepositoryCandidateProvider;
use Nowo\DeviceIntelligence\Matching\IndexKeyFactory;
use Nowo\DeviceIntelligence\Matching\MatchingConfig;
use Nowo\DeviceIntelligence\Matching\MatchingWeights;
use Nowo\DeviceIntelligence\Matching\WeightedDeviceMatcher;
use Nowo\DeviceIntelligence\Port\DeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Port\DeviceUserRepositoryInterface;
use Nowo\DeviceIntelligence\Port\GeoIpProviderInterface;
use Nowo\DeviceIntelligence\Port\IpReputationProviderInterface;
use Nowo\DeviceIntelligence\Port\MetricsRecorderInterface;
use Nowo\DeviceIntelligence\Port\NullGeoIpProvider;
use Nowo\DeviceIntelligence\Port\NullIpReputationProvider;
use Nowo\DeviceIntelligence\Port\NullMetricsRecorder;
use Nowo\DeviceIntelligence\Port\ObservationRepositoryInterface;
use Nowo\DeviceIntelligence\Port\TrustedDeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Privacy\AllowAllConsentGate;
use Nowo\DeviceIntelligence\Privacy\ConsentGateInterface;
use Nowo\DeviceIntelligence\Privacy\PrivacyContext;
use Nowo\DeviceIntelligence\Privacy\PrivacyMode;
use Nowo\DeviceIntelligence\Privacy\PrivacyProcessor;
use Nowo\DeviceIntelligence\Privacy\PrivacyProcessorInterface;
use Nowo\DeviceIntelligence\Risk\RiskEngine;
use Nowo\DeviceIntelligence\Risk\RiskLevels;
use Nowo\DeviceIntelligence\Risk\RiskRuleInterface;
use Nowo\DeviceIntelligence\Risk\ThresholdRiskDecision;
use Nowo\DeviceIntelligence\Signal\Normalizer\SignalNormalizerRegistry;
use Nowo\DeviceIntelligence\Signal\Server\DefaultServerSignalProvider;
use Nowo\DeviceIntelligence\Signal\Server\NetworkSignalProviderInterface;
use Nowo\DeviceIntelligence\Signal\Server\NullNetworkSignalProvider;
use Nowo\DeviceIntelligence\Signal\Server\ServerSignalProviderInterface;
use Nowo\DeviceIntelligence\User\UserIdentifierResolverInterface;
use Nowo\DeviceIntelligence\Velocity\CacheVelocityEngine;
use Nowo\DeviceIntelligence\Velocity\InMemoryVelocityEngine;
use Nowo\DeviceIntelligence\Velocity\VelocityEngineInterface;
use Nowo\DeviceIntelligenceBundle\Attribute\AsDeviceRiskRule;
use Nowo\DeviceIntelligenceBundle\Config\DeviceIntelligenceConfig;
use Nowo\DeviceIntelligenceBundle\DependencyInjection\Compiler\PersonalDataBridgePass;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineDeviceRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineDeviceUserRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineObservationRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineTrustedDeviceRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\TablePrefixSubscriber;
use Nowo\DeviceIntelligenceBundle\EventSubscriber\RequestStateResetSubscriber;
use Nowo\DeviceIntelligenceBundle\Infrastructure\SystemClock;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataEraser;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataEraserInterface;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataExporter;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataExporterInterface;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataSubjectResolverInterface;
use Nowo\DeviceIntelligenceBundle\PersonalData\UserIdentifierSubjectResolver;
use Nowo\DeviceIntelligenceBundle\PersonalData\UserKit\DeviceIntelligenceUserKitEraser;
use Nowo\DeviceIntelligenceBundle\PersonalData\UserKit\DeviceIntelligenceUserKitExportProvider;
use Nowo\DeviceIntelligenceBundle\Profiler\DeviceIntelligenceDataCollector;
use Nowo\DeviceIntelligenceBundle\Risk\RiskEngineFactory;
use Nowo\DeviceIntelligenceBundle\User\SecurityUserIdentifierResolver;
use Nowo\UserKitBundle\PersonalData\PersonalDataEraserInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Asset\Package;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\Translation\Translator;

/**
 * Processes nowo_device_intelligence config and wires core + Doctrine services.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class NowoDeviceIntelligenceExtension extends Extension implements PrependExtensionInterface
{
    /** Container parameter holding `doctrine.entity_manager` (null: the default entity manager), 1.7. */
    public const ENTITY_MANAGER_PARAMETER = 'nowo_device_intelligence.doctrine.entity_manager';

    /**
     * Defined only when `doctrine.enabled` is true (1.7): the entity mapping pass is registered only then (Symfony's
     * mapping passes test the presence of their "enabled" parameter, not its value).
     */
    public const MAPPING_ENABLED_PARAMETER = 'nowo_device_intelligence.doctrine.mapping_enabled';

    private const SIGNAL_NORMALIZERS = 'nowo_device_intelligence.signal_normalizers';

    /**
     * Returns the alias.
     */
    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }

    /**
     * Prepends the framework configuration the bundle relies on.
     *
     * The asset package and the translation path are only prepended when symfony/asset and symfony/translation are
     * installed: setting `framework.assets` / `framework.translator` enables those features, and without their
     * component the host kernel fails to compile (Symfony <= 8.1: "Asset support cannot be enabled"; Symfony 8.2:
     * "The framework.assets configuration is handled by the asset extension, which is not registered").
     */
    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('framework')) {
            return;
        }

        $framework = [];
        if (class_exists(Package::class)) {
            $framework['assets'] = [
                'packages' => [
                    Configuration::ALIAS => [
                        'base_path' => '/bundles/nowodeviceintelligence',
                    ],
                ],
            ];
        }

        if (class_exists(Translator::class)) {
            $framework['translator'] = [
                'paths' => [
                    __DIR__.'/../Resources/translations',
                ],
            ];
        }

        if ([] !== $framework) {
            $container->prependExtensionConfig('framework', $framework);
        }
    }

    /**
     * Processes the configuration and loads the bundle services.
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $envParam = $container->hasParameter('kernel.environment')
            ? $container->getParameter('kernel.environment')
            : 'dev';
        $env = \is_string($envParam) ? $envParam : 'dev';
        if ('prod' === $env && ($config['endpoint']['csrf'] ?? 'origin') === 'none') {
            throw new InvalidConfigurationException('nowo_device_intelligence.endpoint.csrf cannot be "none" in the prod environment. Use "origin" or "double_submit".');
        }

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.php');

        $this->setParameters($container, $config);

        $container->registerForAutoconfiguration(RiskRuleInterface::class)
            ->addTag('nowo.device_intelligence.risk_rule');

        $container->registerAttributeForAutoconfiguration(
            AsDeviceRiskRule::class,
            static function (ChildDefinition $definition, AsDeviceRiskRule $attribute): void {
                $definition->addTag('nowo.device_intelligence.risk_rule', [
                    'priority' => $attribute->priority,
                ]);
            },
        );

        if (!$container->has(ClockInterface::class) && !$container->has('clock')) {
            $container->setAlias(ClockInterface::class, SystemClock::class);
        } elseif ($container->has('clock') && !$container->has(ClockInterface::class)) {
            $container->setAlias(ClockInterface::class, 'clock');
        }

        $profile = $config['profiles'][$config['default_profile']];
        $this->registerCore($container, $config, $profile);
        $this->registerRepositories($container, $config);
        $this->registerPersonalData($container, $config);
        $this->registerProfiler($container, $config);
    }

    /**
     * GDPR erasure / export ports (1.9): Doctrine defaults with `doctrine.enabled` (a host without Doctrine binds its
     * own), the subject resolver, and the UserKit adapters when UserKit is installed and `personal_data.enabled` is
     * true. {@see PersonalDataBridgePass} drops an adapter whose port is not bound.
     *
     * @param array<string, mixed> $config
     */
    private function registerPersonalData(ContainerBuilder $container, array $config): void
    {
        if ($config['doctrine']['enabled']) {
            $container->register(DeviceIntelligencePersonalDataEraser::class)
                ->setArguments([
                    new Reference('doctrine'),
                    new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference(ClockInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ]);
            $container->setAlias(DeviceIntelligencePersonalDataEraserInterface::class, DeviceIntelligencePersonalDataEraser::class)->setPublic(true);
            $container->register(DeviceIntelligencePersonalDataExporter::class)
                ->setArguments([
                    new Reference('doctrine'),
                    new Reference(ClockInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ]);
            $container->setAlias(DeviceIntelligencePersonalDataExporterInterface::class, DeviceIntelligencePersonalDataExporter::class)->setPublic(true);
        }

        $personalData = $config['personal_data'];
        $container->setParameter('nowo_device_intelligence.personal_data.enabled', $personalData['enabled']);
        $container->register(UserIdentifierSubjectResolver::class);
        $resolver = \is_string($personalData['subject_resolver']) ? $personalData['subject_resolver'] : UserIdentifierSubjectResolver::class;
        $container->setAlias(DeviceIntelligencePersonalDataSubjectResolverInterface::class, $resolver)->setPublic(true);

        if (!$personalData['enabled'] || !interface_exists(PersonalDataEraserInterface::class)) {
            return;
        }

        $container->register(DeviceIntelligenceUserKitEraser::class)
            ->setArguments([
                new Reference(DeviceIntelligencePersonalDataEraserInterface::class),
                new Reference(DeviceIntelligencePersonalDataSubjectResolverInterface::class),
            ])
            ->addTag(PersonalDataBridgePass::ERASER_TAG, ['priority' => DeviceIntelligenceUserKitEraser::PRIORITY]);
        $container->register(DeviceIntelligenceUserKitExportProvider::class)
            ->setArguments([
                new Reference(DeviceIntelligencePersonalDataExporterInterface::class),
                new Reference(DeviceIntelligencePersonalDataSubjectResolverInterface::class),
            ])
            ->addTag(PersonalDataBridgePass::EXPORT_PROVIDER_TAG, ['priority' => DeviceIntelligenceUserKitExportProvider::PRIORITY]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function setParameters(ContainerBuilder $container, array $config): void
    {
        $container->setParameter('nowo_device_intelligence.enabled', $config['enabled']);
        $container->setParameter('nowo_device_intelligence.default_profile', $config['default_profile']);
        $container->setParameter('nowo_device_intelligence.profiles', $config['profiles']);
        $container->setParameter('nowo_device_intelligence.endpoint', $config['endpoint']);
        $container->setParameter('nowo_device_intelligence.endpoint.enabled', $config['endpoint']['enabled']);
        $container->setParameter('nowo_device_intelligence.endpoint.path', $config['endpoint']['path']);
        $container->setParameter('nowo_device_intelligence.doctrine.enabled', $config['doctrine']['enabled']);
        $container->setParameter('nowo_device_intelligence.doctrine.table_prefix', $config['doctrine']['table_prefix']);
        if ($config['doctrine']['enabled']) {
            $container->setParameter(self::MAPPING_ENABLED_PARAMETER, true);
        }
        $entityManagerName = $config['doctrine']['entity_manager'] ?? null;
        $container->setParameter(self::ENTITY_MANAGER_PARAMETER, \is_string($entityManagerName) && '' !== $entityManagerName && $config['doctrine']['enabled'] ? $entityManagerName : null);
        $container->setParameter('nowo_device_intelligence.cache.pool', $config['cache']['pool']);
        $container->setParameter('nowo_device_intelligence.messenger.enabled', $config['messenger']['enabled']);
        $container->setParameter('nowo_device_intelligence.profiler', $config['profiler']);
        $container->setParameter('nowo_device_intelligence.observe_on_every_request', $config['observe_on_every_request']);
        $container->setParameter('nowo_device_intelligence.token_cookie', $config['token_cookie']);
        $container->setParameter('nowo_device_intelligence.token_ttl', $config['token_ttl']);
        $container->setParameter('nowo_device_intelligence.ip_salt', $config['ip_salt']);

        $container->register(DeviceIntelligenceConfig::class)
            ->setArguments([$config])
            ->setPublic(false);
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $profile
     */
    private function registerCore(ContainerBuilder $container, array $config, array $profile): void
    {
        $weights = new Definition(MatchingWeights::class, [$profile['matching']['weights']]);
        $matching = new Definition(MatchingConfig::class, [
            $weights,
            $profile['matching']['minimum_confidence'],
            $profile['matching']['candidate_limit'],
            $profile['matching']['on_low_confidence'],
            $profile['matching']['lookback'],
        ]);
        $container->setDefinition(MatchingConfig::class, $matching);

        $container->register(WeightedDeviceMatcher::class)
            ->setArguments([new Reference(MatchingConfig::class)])
            ->setPublic(false);

        $levels = $profile['risk']['levels'];
        $container->register(RiskLevels::class)
            ->setArguments([$levels['medium'], $levels['high'], $levels['critical']]);

        $container->register(ThresholdRiskDecision::class)
            ->setArguments([
                $profile['risk']['decisions']['observe'],
                $profile['risk']['decisions']['step_up'],
                $profile['risk']['decisions']['block'],
            ]);

        $rulesConfig = $profile['risk']['enabled'] ? $profile['risk']['rules'] : [];
        $container->register(RiskEngine::class)
            ->setFactory([RiskEngineFactory::class, 'create'])
            ->setArguments([
                new TaggedIteratorArgument('nowo.device_intelligence.risk_rule'),
                $profile['risk']['enabled'] ? $rulesConfig : self::disableAllRules($rulesConfig),
                new Reference(RiskLevels::class),
            ])
            ->setPublic(false);

        $privacy = $profile['privacy'];
        $container->register(PrivacyContext::class)
            ->setArguments([
                PrivacyMode::from((string) $privacy['mode']),
                (bool) $privacy['high_entropy_consent'],
                (bool) $privacy['hash_ip'],
                (bool) $privacy['store_raw_ip'],
                (bool) $privacy['store_user_agent'],
            ]);

        $this->registerCorePorts($container);

        // Same collaborators as DeviceIntelligence::create(), each one resolved through its port alias so a host can
        // swap a single adapter (GeoIP, metrics, labeler, network signals, privacy...) without redefining the service.
        $container->register(DeviceIntelligence::class)
            ->setArguments([
                new Reference(DeviceRepositoryInterface::class),
                new Reference(ObservationRepositoryInterface::class),
                new Reference(DeviceUserRepositoryInterface::class),
                new Reference(TrustedDeviceRepositoryInterface::class),
                new Reference(WeightedDeviceMatcher::class),
                new Reference(DeviceCandidateProviderInterface::class),
                new Reference(self::SIGNAL_NORMALIZERS),
                new Reference(PrivacyProcessorInterface::class),
                new Reference(RiskEngine::class),
                new Reference(DeviceLabelerInterface::class),
                new Reference(ServerSignalProviderInterface::class),
                new Reference(NetworkSignalProviderInterface::class),
                new Reference(GeoIpProviderInterface::class),
                new Reference(VelocityEngineInterface::class),
                new Reference(MetricsRecorderInterface::class),
                new Reference(IndexKeyFactory::class),
                new Reference(ConsentGateInterface::class),
                new Reference(IpReputationProviderInterface::class),
            ])
            ->setShared(false)
            ->setPublic(true);

        $container->setAlias(DeviceIntelligenceInterface::class, DeviceIntelligence::class)
            ->setPublic(true);

        $container->register(DeviceManager::class)
            ->setArguments([
                new Reference(DeviceRepositoryInterface::class),
                new Reference(DeviceUserRepositoryInterface::class),
                new Reference(TrustedDeviceRepositoryInterface::class),
                new Reference(ClockInterface::class),
                null,
            ])
            ->setShared(false)
            ->setPublic(true);

        $container->setAlias(UserIdentifierResolverInterface::class, SecurityUserIdentifierResolver::class);

        $container->register(CacheVelocityEngine::class)
            ->setArguments([
                new Reference('nowo_device_intelligence.simple_cache'),
                'di.vel.',
            ]);
        $container->setAlias(VelocityEngineInterface::class, CacheVelocityEngine::class);

        $container->register(InMemoryVelocityEngine::class)->setShared(false);

        if ($container->hasDefinition('nowo_device_intelligence.simple_cache')) {
            $container->getDefinition('nowo_device_intelligence.simple_cache')
                ->setArgument(0, new Reference((string) $config['cache']['pool']));
        }
    }

    /**
     * Registers the default adapters of the core ports used by DeviceIntelligence and aliases each port to them.
     *
     * The defaults are the implementations DeviceIntelligence::create() hard-codes (Null providers, the default
     * labeler and privacy processor), so the analysis is unchanged until the host overrides an alias.
     */
    private function registerCorePorts(ContainerBuilder $container): void
    {
        $container->register(IndexKeyFactory::class)->setPublic(false);
        $container->register(self::SIGNAL_NORMALIZERS, SignalNormalizerRegistry::class)
            ->setFactory([SignalNormalizerRegistry::class, 'defaults'])
            ->setPublic(false);
        $container->register(RepositoryCandidateProvider::class)
            ->setArguments([
                new Reference(DeviceRepositoryInterface::class),
                new Reference(IndexKeyFactory::class),
            ])
            ->setPublic(false);
        $container->setAlias(DeviceCandidateProviderInterface::class, RepositoryCandidateProvider::class);

        $defaults = [
            PrivacyProcessorInterface::class => PrivacyProcessor::class,
            DeviceLabelerInterface::class => DefaultDeviceLabeler::class,
            ServerSignalProviderInterface::class => DefaultServerSignalProvider::class,
            NetworkSignalProviderInterface::class => NullNetworkSignalProvider::class,
            GeoIpProviderInterface::class => NullGeoIpProvider::class,
            MetricsRecorderInterface::class => NullMetricsRecorder::class,
            // 1.7 (rule 5): consumed by DeviceIntelligence::analyze() (consent per collector, IP reputation rule).
            ConsentGateInterface::class => AllowAllConsentGate::class,
            IpReputationProviderInterface::class => NullIpReputationProvider::class,
        ];
        foreach ($defaults as $port => $implementation) {
            $container->register($implementation)->setPublic(false);
            $container->setAlias($port, $implementation);
        }
    }

    /**
     * @param array<string, array{enabled: bool, weight?: int|null}> $rules
     *
     * @return array<string, array{enabled: bool, weight?: int|null}>
     */
    private static function disableAllRules(array $rules): array
    {
        $out = [];
        foreach ($rules as $name => $rule) {
            $out[$name] = ['enabled' => false, 'weight' => $rule['weight'] ?? null];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function registerRepositories(ContainerBuilder $container, array $config): void
    {
        if ($config['doctrine']['enabled']) {
            $container->register(TablePrefixSubscriber::class)
                ->setArguments([$config['doctrine']['table_prefix']])
                ->addTag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

            $container->setAlias(DeviceRepositoryInterface::class, DoctrineDeviceRepository::class)
                ->setPublic(true);
            $container->setAlias(ObservationRepositoryInterface::class, DoctrineObservationRepository::class)
                ->setPublic(true);
            $container->setAlias(DeviceUserRepositoryInterface::class, DoctrineDeviceUserRepository::class)
                ->setPublic(true);
            $container->setAlias(TrustedDeviceRepositoryInterface::class, DoctrineTrustedDeviceRepository::class)
                ->setPublic(true);

            return;
        }

        foreach ([InMemoryDeviceRepository::class, InMemoryObservationRepository::class, InMemoryDeviceUserRepository::class, InMemoryTrustedDeviceRepository::class] as $inMemory) {
            $container->register($inMemory)
                ->setShared(true)
                ->addTag('kernel.reset', ['method' => 'reset'])
                ->addTag(RequestStateResetSubscriber::TAG);
        }

        $container->setAlias(DeviceRepositoryInterface::class, InMemoryDeviceRepository::class)->setPublic(true);
        $container->setAlias(ObservationRepositoryInterface::class, InMemoryObservationRepository::class)->setPublic(true);
        $container->setAlias(DeviceUserRepositoryInterface::class, InMemoryDeviceUserRepository::class)->setPublic(true);
        $container->setAlias(TrustedDeviceRepositoryInterface::class, InMemoryTrustedDeviceRepository::class)->setPublic(true);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function registerProfiler(ContainerBuilder $container, array $config): void
    {
        if (!$config['profiler'] || !class_exists(DataCollector::class)) {
            if ($container->hasDefinition(DeviceIntelligenceDataCollector::class)) {
                $container->removeDefinition(DeviceIntelligenceDataCollector::class);
            }

            return;
        }

        if ($container->hasDefinition(DeviceIntelligenceDataCollector::class)) {
            $container->getDefinition(DeviceIntelligenceDataCollector::class)
                ->addTag('data_collector', [
                    'id' => 'nowo_device_intelligence',
                    'template' => '@NowoDeviceIntelligenceBundle/Collector/device_intelligence.html.twig',
                    'priority' => 250,
                ]);
        }
    }
}
