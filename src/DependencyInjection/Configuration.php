<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\DependencyInjection;

use Nowo\DeviceIntelligence\Matching\MatchingWeights;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

use function array_key_exists;
use function is_array;
use function sprintf;

/**
 * Configuration tree for nowo_device_intelligence.
 *
 * Legacy flat collectors/matching/risk keys at the root are normalized into
 * profiles.default before validation.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class Configuration implements ConfigurationInterface
{
    public const ALIAS = 'nowo_device_intelligence';

    public const DEFAULT_PROFILE = 'default';

    /**
     * Collector names accepted in profile configuration (client SDK groups).
     *
     * @var list<string>
     */
    public const ALLOWED_COLLECTORS = [
        'audio',
        'canvas',
        'webgl',
        'screen',
        'navigator',
        'timezone',
        'client_hints',
        'capabilities',
        'automation',
        'fonts',
    ];

    /**
     * Built-in risk rule names from the PHP core.
     *
     * @var list<string>
     */
    public const RISK_RULES = [
        'new_device',
        'multiple_accounts',
        'rapid_account_creation',
        'device_velocity',
        'fingerprint_mutation',
        'automation',
        'suspicious_login',
        'impossible_travel',
        'session_change',
        'ip_change',
        'country_change',
        'trusted_device',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $root        = $treeBuilder->getRootNode();

        $root
            ->beforeNormalization()
                ->always(self::normalizeLegacyRoot(...))
            ->end()
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->scalarNode('default_profile')->defaultValue(self::DEFAULT_PROFILE)->cannotBeEmpty()->end()
                ->append($this->profilesNode())
                ->append($this->endpointNode())
                ->append($this->doctrineNode())
                ->append($this->cacheNode())
                ->append($this->messengerNode())
                ->booleanNode('profiler')->defaultTrue()->end()
                ->booleanNode('observe_on_every_request')->defaultFalse()->end()
                ->append($this->tokenCookieNode())
                ->integerNode('token_ttl')->min(60)->defaultValue(3600)->end()
                ->scalarNode('ip_salt')->defaultValue('')->end()
            ->end()
            ->validate()
                ->always(function (array $config): array {
                    $default = $config['default_profile'];
                    if (!isset($config['profiles'][$default])) {
                        throw new InvalidConfigurationException(sprintf('nowo_device_intelligence.default_profile "%s" does not match any configured profile.', $default));
                    }

                    foreach ($config['profiles'] as $name => $profile) {
                        $this->assertWeights($profile['matching']['weights'], (string) $name);
                    }

                    return $config;
                })
            ->end();

        return $treeBuilder;
    }

    /**
     * Move legacy flat collectors/matching/risk (and siblings) into profiles.default.
     *
     * @param array<string, mixed>|null $config
     *
     * @return array<string, mixed>
     */
    public static function normalizeLegacyRoot(?array $config): array
    {
        $config ??= [];
        $legacyKeys = ['collectors', 'matching', 'risk', 'trusted_devices', 'privacy', 'rate_limit'];
        $extracted  = [];
        foreach ($legacyKeys as $key) {
            if (array_key_exists($key, $config)) {
                $extracted[$key] = $config[$key];
                unset($config[$key]);
            }
        }

        if ($extracted !== []) {
            $config['profiles'] ??= [];
            $existing = is_array($config['profiles'][self::DEFAULT_PROFILE] ?? null)
                ? $config['profiles'][self::DEFAULT_PROFILE]
                : [];
            $config['profiles'][self::DEFAULT_PROFILE] = array_replace_recursive($extracted, $existing);
        }

        if (!isset($config['profiles']) || $config['profiles'] === []) {
            $config['profiles'] = [self::DEFAULT_PROFILE => []];
        }

        if (!isset($config['default_profile'])) {
            $names                     = array_keys($config['profiles']);
            $config['default_profile'] = $names[0] ?? self::DEFAULT_PROFILE;
        }

        return $config;
    }

    private function profilesNode(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('profiles'))->getRootNode();
        $node
            ->useAttributeAsKey('name')
            ->defaultValue([self::DEFAULT_PROFILE => []])
            ->arrayPrototype()
                ->children()
                    ->arrayNode('collectors')
                        ->scalarPrototype()
                            ->validate()
                                ->ifNotInArray(self::ALLOWED_COLLECTORS)
                                ->thenInvalid('Unknown collector "%s". Allowed: ' . implode(', ', self::ALLOWED_COLLECTORS) . '.')
                            ->end()
                        ->end()
                        ->defaultValue([
                            'audio',
                            'canvas',
                            'webgl',
                            'screen',
                            'navigator',
                            'timezone',
                            'client_hints',
                            'capabilities',
                            'automation',
                        ])
                    ->end()
                    ->append($this->matchingNode())
                    ->append($this->riskNode())
                    ->append($this->trustedDevicesNode())
                    ->append($this->privacyNode())
                    ->append($this->rateLimitNode())
                ->end()
            ->end();

        return $node;
    }

    private function matchingNode(): ArrayNodeDefinition
    {
        $defaults = MatchingWeights::defaults()->weights;
        $node     = (new TreeBuilder('matching'))->getRootNode();
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->floatNode('minimum_confidence')->min(0.0)->max(1.0)->defaultValue(0.75)->end()
                ->arrayNode('weights')
                    ->useAttributeAsKey('name')
                    ->defaultValue($defaults)
                    ->floatPrototype()->min(0.0)->max(1.0)->end()
                ->end()
                ->integerNode('candidate_limit')->min(1)->max(1000)->defaultValue(64)->end()
                ->scalarNode('lookback')->defaultValue('P180D')->cannotBeEmpty()->end()
                ->enumNode('on_low_confidence')
                    ->values(['new_device', 'reject'])
                    ->defaultValue('new_device')
                ->end()
            ->end();

        return $node;
    }

    private function riskNode(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('risk'))->getRootNode();
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->arrayNode('levels')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('low')->min(0)->max(100)->defaultValue(0)->end()
                        ->integerNode('medium')->min(0)->max(100)->defaultValue(30)->end()
                        ->integerNode('high')->min(0)->max(100)->defaultValue(65)->end()
                        ->integerNode('critical')->min(0)->max(100)->defaultValue(90)->end()
                    ->end()
                ->end()
                ->arrayNode('decisions')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('observe')->min(0)->max(100)->defaultValue(40)->end()
                        ->integerNode('step_up')->min(0)->max(100)->defaultValue(70)->end()
                        ->integerNode('block')->min(0)->max(100)->defaultValue(90)->end()
                    ->end()
                ->end()
                ->arrayNode('rules')
                    ->useAttributeAsKey('name')
                    ->defaultValue($this->defaultRiskRules())
                    ->arrayPrototype()
                        ->children()
                            ->booleanNode('enabled')->defaultTrue()->end()
                            ->variableNode('weight')
                                ->defaultNull()
                                ->validate()
                                    ->ifTrue(static fn (mixed $v): bool => $v !== null && (!is_numeric($v) || (int) $v < 0 || (int) $v > 100))
                                    ->thenInvalid('Risk rule weight must be null or an integer 0..100.')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    /**
     * @return array<string, array{enabled: bool, weight: int|null}>
     */
    private function defaultRiskRules(): array
    {
        $out = [];
        foreach (self::RISK_RULES as $name) {
            $out[$name] = ['enabled' => true, 'weight' => null];
        }

        return $out;
    }

    private function trustedDevicesNode(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('trusted_devices'))->getRootNode();
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->scalarNode('default_ttl')->defaultValue('P90D')->end()
            ->end();

        return $node;
    }

    private function privacyNode(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('privacy'))->getRootNode();
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->enumNode('mode')->values(['strict', 'balanced', 'full'])->defaultValue('balanced')->end()
                ->booleanNode('hash_ip')->defaultTrue()->end()
                ->booleanNode('store_raw_ip')->defaultFalse()->end()
                ->booleanNode('store_user_agent')->defaultTrue()->end()
                ->booleanNode('high_entropy_consent')->defaultTrue()->end()
            ->end();

        return $node;
    }

    private function rateLimitNode(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('rate_limit'))->getRootNode();
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('policies')
                    ->useAttributeAsKey('name')
                    ->defaultValue([
                        'collect' => ['limit' => 60, 'interval' => '1 minute'],
                    ])
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('limit')->min(1)->defaultValue(60)->end()
                            ->scalarNode('interval')->defaultValue('1 minute')->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    private function endpointNode(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('endpoint'))->getRootNode();
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->scalarNode('path')->defaultValue('/_device/collect')->cannotBeEmpty()->end()
                ->enumNode('csrf')->values(['origin', 'double_submit', 'none'])->defaultValue('origin')->end()
                ->integerNode('max_payload_bytes')->min(1024)->defaultValue(65536)->end()
                ->integerNode('timestamp_skew')->min(0)->defaultValue(300)->end()
                ->booleanNode('replay_protection')->defaultTrue()->end()
                ->arrayNode('allowed_origins')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('response')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('device_id')->defaultFalse()->end()
                        ->booleanNode('confidence')->defaultFalse()->end()
                        ->booleanNode('risk')->defaultFalse()->end()
                        ->booleanNode('token')->defaultTrue()->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    private function doctrineNode(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('doctrine'))->getRootNode();
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->scalarNode('table_prefix')->defaultValue('device_intelligence_')->end()
            ->end();

        return $node;
    }

    private function cacheNode(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('cache'))->getRootNode();
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('pool')->defaultValue('cache.app')->cannotBeEmpty()->end()
            ->end();

        return $node;
    }

    private function messengerNode(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('messenger'))->getRootNode();
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')->defaultFalse()->end()
                ->scalarNode('transport')->defaultNull()->end()
            ->end();

        return $node;
    }

    private function tokenCookieNode(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('token_cookie'))->getRootNode();
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('name')->defaultValue('di_obs')->cannotBeEmpty()->end()
                ->scalarNode('path')->defaultValue('/')->end()
                ->scalarNode('domain')->defaultNull()->end()
                ->scalarNode('secure')->defaultValue('auto')->end()
                ->booleanNode('httponly')->defaultTrue()->end()
                ->enumNode('samesite')->values(['lax', 'strict', 'none'])->defaultValue('lax')->end()
            ->end();

        return $node;
    }

    /**
     * @param array<string, float|int> $weights
     */
    private function assertWeights(array $weights, string $profile): void
    {
        $sum = 0.0;
        foreach ($weights as $name => $weight) {
            $value = (float) $weight;
            if ($value < 0.0 || $value > 1.0) {
                throw new InvalidConfigurationException(sprintf('Matching weight "%s" in profile "%s" must be in [0, 1].', $name, $profile));
            }
            $sum += $value;
        }
        if (abs($sum - 1.0) > 0.001) {
            throw new InvalidConfigurationException(sprintf('Matching weights in profile "%s" must sum to ~1.0, got %s.', $profile, (string) $sum));
        }
    }
}
