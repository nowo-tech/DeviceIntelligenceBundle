<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Risk;

use Nowo\DeviceIntelligence\Risk\RiskLevels;
use Nowo\DeviceIntelligence\Risk\RiskResult;
use Nowo\DeviceIntelligence\Risk\RiskRuleInterface;
use Nowo\DeviceIntelligenceBundle\Risk\RiskEngineFactory;
use Nowo\DeviceIntelligenceBundle\Tests\Support\Scenario;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class RiskEngineFactoryTest extends TestCase
{
    public function testDefaultRulesAndTaggedOverride(): void
    {
        $defaults = RiskEngineFactory::defaultRules();
        self::assertNotSame([], $defaults);

        $custom = $this->createMock(RiskRuleInterface::class);
        $custom->method('name')->willReturn($defaults[0]->name());
        $custom->method('evaluate')->willReturn(new RiskResult(0, $defaults[0]->name()));

        $engine = RiskEngineFactory::create([$custom], [
            $defaults[0]->name() => ['enabled' => true],
        ], new RiskLevels());

        $score = $engine->assess(Scenario::context(
            Scenario::device(),
            Scenario::observation(Scenario::device()),
            true,
        ));
        self::assertGreaterThanOrEqual(0, $score->score());
    }
}
