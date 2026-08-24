<?php

declare(strict_types=1);

namespace App\Risk;

use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Risk\RiskResult;
use Nowo\DeviceIntelligence\Risk\RiskRuleInterface;
use Nowo\DeviceIntelligenceBundle\Attribute\AsDeviceRiskRule;

/**
 * Demo custom rule: signed-in user "vip" lowers the score.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsDeviceRiskRule(priority: 10)]
final class DemoVipAllowlistRule implements RiskRuleInterface
{
    public function name(): string
    {
        return 'vip_allowlist';
    }

    public function evaluate(RiskContext $context): RiskResult
    {
        if ('vip' === $context->observation->userIdentifier?->value) {
            return new RiskResult(-25, $this->name(), ['vip' => true]);
        }

        return new RiskResult(0, $this->name());
    }
}
