<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Risk;

use Nowo\DeviceIntelligence\Risk\RiskEngine;
use Nowo\DeviceIntelligence\Risk\RiskLevels;
use Nowo\DeviceIntelligence\Risk\RiskRuleInterface;
use Nowo\DeviceIntelligence\Risk\Rule\AutomationRule;
use Nowo\DeviceIntelligence\Risk\Rule\CountryChangeRule;
use Nowo\DeviceIntelligence\Risk\Rule\DeviceVelocityRule;
use Nowo\DeviceIntelligence\Risk\Rule\FingerprintMutationRule;
use Nowo\DeviceIntelligence\Risk\Rule\ImpossibleTravelRule;
use Nowo\DeviceIntelligence\Risk\Rule\IpChangeRule;
use Nowo\DeviceIntelligence\Risk\Rule\MultipleAccountsRule;
use Nowo\DeviceIntelligence\Risk\Rule\NewDeviceRule;
use Nowo\DeviceIntelligence\Risk\Rule\RapidAccountCreationRule;
use Nowo\DeviceIntelligence\Risk\Rule\SessionChangeRule;
use Nowo\DeviceIntelligence\Risk\Rule\SuspiciousLoginRule;
use Nowo\DeviceIntelligence\Risk\Rule\TrustedDeviceRule;

/**
 * Builds a RiskEngine from tagged custom rules or the core defaults.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class RiskEngineFactory
{
    /**
     * @param iterable<RiskRuleInterface>                            $tagged
     * @param array<string, array{enabled: bool, weight?: int|null}> $rulesConfig
     */
    public static function create(iterable $tagged, array $rulesConfig, RiskLevels $levels): RiskEngine
    {
        $byName = [];
        foreach (self::defaultRules() as $rule) {
            $byName[$rule->name()] = $rule;
        }
        foreach ($tagged as $rule) {
            $byName[$rule->name()] = $rule;
        }

        return new RiskEngine(array_values($byName), $rulesConfig, $levels);
    }

    /**
     * Same set as {@see RiskEngine::defaults()} — not a reimplementation of scoring.
     *
     * @return list<RiskRuleInterface>
     */
    public static function defaultRules(): array
    {
        return [
            new NewDeviceRule(),
            new MultipleAccountsRule(),
            new RapidAccountCreationRule(),
            new DeviceVelocityRule(),
            new FingerprintMutationRule(),
            new AutomationRule(),
            new SuspiciousLoginRule(),
            new ImpossibleTravelRule(),
            new SessionChangeRule(),
            new IpChangeRule(),
            new CountryChangeRule(),
            new TrustedDeviceRule(),
        ];
    }
}
