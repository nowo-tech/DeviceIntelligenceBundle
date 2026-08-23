<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\DependencyInjection;

use Nowo\DeviceIntelligence\Device\DeviceManager;
use Nowo\DeviceIntelligence\DeviceIntelligence;
use Nowo\DeviceIntelligence\DeviceIntelligenceInterface;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceUserRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryObservationRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryTrustedDeviceRepository;
use Nowo\DeviceIntelligence\Matching\MatchingConfig;
use Nowo\DeviceIntelligence\Matching\MatchingWeights;
use Nowo\DeviceIntelligence\Matching\WeightedDeviceMatcher;
use Nowo\DeviceIntelligence\Port\DeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Port\DeviceUserRepositoryInterface;
use Nowo\DeviceIntelligence\Port\ObservationRepositoryInterface;
use Nowo\DeviceIntelligence\Port\TrustedDeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Privacy\PrivacyContext;
use Nowo\DeviceIntelligence\Privacy\PrivacyMode;
use Nowo\DeviceIntelligence\Risk\RiskEngine;
use Nowo\DeviceIntelligence\Risk\RiskLevels;
use Nowo\DeviceIntelligence\Risk\RiskRuleInterface;
use Nowo\DeviceIntelligence\Risk\ThresholdRiskDecision;
use Nowo\DeviceIntelligence\User\UserIdentifierResolverInterface;
use Nowo\DeviceIntelligence\Velocity\CacheVelocityEngine;
use Nowo\DeviceIntelligence\Velocity\InMemoryVelocityEngine;
use Nowo\DeviceIntelligence\Velocity\VelocityEngineInterface;
use Nowo\DeviceIntelligenceBundle\Attribute\AsDeviceRiskRule;
use Nowo\DeviceIntelligenceBundle\Config\DeviceIntelligenceConfig;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineDeviceRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineDeviceUserRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineObservationRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineTrustedDeviceRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\TablePrefixSubscriber;
use Nowo\DeviceIntelligenceBundle\Infrastructure\SystemClock;
use Nowo\DeviceIntelligenceBundle\Profiler\DeviceIntelligenceDataCollector;
use Nowo\DeviceIntelligenceBundle\Risk\RiskEngineFactory;
use Nowo\DeviceIntelligenceBundle\User\SecurityUserIdentifierResolver;
use Psr\Clock\ClockInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Processes nowo_device_intelligence config and wires core + Doctrine services.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class NowoDeviceIntelligenceExtension extends Extension implements PrependExtensionInterface
{
    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }

    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('twig')) {
            $container->prependExtensionConfig('twig', [
                'paths' => [
                    \dirname(__DIR__).'/Resources/views' => 'NowoDeviceIntelligenceBundle',
                ],
            ]);
        }

        if ($container->hasExtension('framework')) {
            $container->prependExtensionConfig('framework', [
                'assets' => [
                    'packages' => [
                        Configuration::ALIAS => [
                            'base_path' => '/bundles/nowodeviceintelligence',
                        ],
                    ],
                ],
            ]);
        }
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));
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
        $this->registerProfiler($container, $config);
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

        $container->register(DeviceIntelligence::class)
            ->setFactory([DeviceIntelligence::class, 'create'])
            ->setArguments([
                new Reference(DeviceRepositoryInterface::class),
                new Reference(ObservationRepositoryInterface::class),
                new Reference(DeviceUserRepositoryInterface::class),
                new Reference(TrustedDeviceRepositoryInterface::class),
                new Reference(WeightedDeviceMatcher::class),
                new Reference(RiskEngine::class),
                new Reference(VelocityEngineInterface::class),
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

        $container->register(InMemoryDeviceRepository::class)->setShared(true);
        $container->register(InMemoryObservationRepository::class)->setShared(true);
        $container->register(InMemoryDeviceUserRepository::class)->setShared(true);
        $container->register(InMemoryTrustedDeviceRepository::class)->setShared(true);

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
