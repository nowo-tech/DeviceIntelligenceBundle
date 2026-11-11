<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk\Rule;

use Nowo\DeviceIntelligence\Port\IpReputationProviderInterface;
use Nowo\DeviceIntelligence\Port\NullIpReputationProvider;
use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Risk\RiskResult;
use Nowo\DeviceIntelligence\Risk\RiskRuleInterface;
use Nowo\DeviceIntelligence\Risk\RiskSeverity;

/**
 * Scores the client IP reputation from {@see IpReputationProviderInterface} (1.7): Tor,
 * anonymising proxies, VPNs and hosting ranges, plus the provider's own risk (0..100). Scores 0 without a provider (the
 * default {@see NullIpReputationProvider}), so the analysis is unchanged until a host wires
 * one.
 */
final class IpReputationRule implements RiskRuleInterface
{
    public function name(): string
    {
        return 'ip_reputation';
    }

    public function evaluate(RiskContext $context): RiskResult
    {
        $reputation = $context->reputation;
        if (null === $reputation) {
            return new RiskResult(0, $this->name());
        }

        $reasons = [];
        $score = 0;
        foreach (['tor' => 30, 'proxy' => 20, 'vpn' => 10, 'hosting' => 10] as $flag => $points) {
            if ($reputation->{$flag}) {
                $reasons[] = $flag;
                $score += $points;
            }
        }
        $score = min(40, max($score, intdiv(max(0, min(100, $reputation->risk)), 3)));
        if (0 === $score) {
            return new RiskResult(0, $this->name());
        }

        return new RiskResult($score, $this->name(), ['flags' => $reasons], $score >= 30 ? RiskSeverity::High : RiskSeverity::Medium);
    }
}
