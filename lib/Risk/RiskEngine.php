<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Risk;

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

final class RiskEngine implements RiskEngineInterface
{
    /**
     * @param list<RiskRuleInterface> $rules
     * @param array<string, array{enabled: bool, weight?: int|null}> $config
     */
    public function __construct(
        private array $rules,
        private array $config = [],
        private RiskLevels $levels = new RiskLevels(),
    ) {
    }

    public static function defaults(): self
    {
        return new self([
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
        ]);
    }

    public function assess(RiskContext $context): RiskAssessment
    {
        $sum     = 0;
        $reasons = [];
        foreach ($this->rules as $rule) {
            $cfg = $this->config[$rule->name()] ?? ['enabled' => true, 'weight' => null];
            if (false === ($cfg['enabled'] ?? true)) {
                continue;
            }
            $result = $rule->evaluate($context);
            if ($result->skipped || $result->scoreContribution === 0) {
                continue;
            }
            $weight       = $cfg['weight'] ?? $result->scoreContribution;
            $contribution = $result->scoreContribution;
            if (null !== ($cfg['weight'] ?? null)) {
                $sign         = $result->scoreContribution < 0 ? -1 : 1;
                $contribution = $sign * abs((int) $weight);
            }
            $sum += $contribution;
            $reasons[] = new RiskReason($result->reason, $contribution, $result->severity);
        }
        $score = RiskScore::clamp($sum);

        return new RiskAssessment($score, $this->levels->levelFor($score->value), $reasons);
    }
}
