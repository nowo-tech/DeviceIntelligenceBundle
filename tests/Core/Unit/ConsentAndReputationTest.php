<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Tests\Core\Unit;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\AnalysisInput;
use Nowo\DeviceIntelligence\DeviceIntelligence;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceUserRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryObservationRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryTrustedDeviceRepository;
use Nowo\DeviceIntelligence\Port\IpReputation;
use Nowo\DeviceIntelligence\Port\IpReputationProviderInterface;
use Nowo\DeviceIntelligence\Privacy\ConsentContext;
use Nowo\DeviceIntelligence\Privacy\ConsentGateInterface;
use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Risk\RiskEngine;
use Nowo\DeviceIntelligence\Risk\RiskReason;
use Nowo\DeviceIntelligence\Risk\Rule\IpReputationRule;
use Nowo\DeviceIntelligence\Signal\SignalName;
use Nowo\DeviceIntelligenceBundle\Tests\Support\Scenario;
use PHPUnit\Framework\TestCase;

/**
 * 1.7 (rule 5): the consent gate and the IP reputation port are consumed by the analysis.
 */
final class ConsentAndReputationTest extends TestCase
{
    public function testConsentGateRemovesTheCollectorsItRefuses(): void
    {
        $gate = new class implements ConsentGateInterface {
            /** @var list<string> */
            public array $asked = [];

            public function allows(string $collector, ConsentContext $context): bool
            {
                $this->asked[] = $collector;

                return 'canvas' !== $collector;
            }
        };
        $engine = $this->engine(consent: $gate);

        $analysis = $engine->analyze(new AnalysisInput(
            new DateTimeImmutable(),
            Scenario::bag(
                Scenario::signal(SignalName::Canvas, 'canvas-hash'),
                Scenario::signal(SignalName::Timezone, 'Europe/Madrid'),
            ),
            '203.0.113.7',
        ));

        self::assertContains('canvas', $gate->asked);
        self::assertFalse($analysis->signals->has(SignalName::Canvas), 'refused collector removed');
        self::assertTrue($analysis->signals->has(SignalName::Timezone));
    }

    public function testReputationReachesTheRiskRule(): void
    {
        $provider = new class implements IpReputationProviderInterface {
            public ?string $ip = null;

            public function inspect(string $ip): IpReputation
            {
                $this->ip = $ip;

                return new IpReputation(tor: true, residential: false);
            }
        };
        $engine = $this->engine(reputation: $provider);

        $analysis = $engine->analyze(new AnalysisInput(new DateTimeImmutable(), Scenario::bag(), '203.0.113.7'));

        self::assertSame('203.0.113.7', $provider->ip);
        $reasons = array_map(static fn (RiskReason $reason): string => $reason->code, $analysis->risk->reasons);
        self::assertContains('ip_reputation', $reasons);
    }

    public function testIpReputationRuleScores(): void
    {
        $rule = new IpReputationRule();
        $base = Scenario::context(Scenario::device(), Scenario::observation(Scenario::device()));
        $context = static fn (?IpReputation $reputation): RiskContext => new RiskContext($base->observation, $base->device, $base->match, reputation: $reputation);

        self::assertSame('ip_reputation', $rule->name());
        self::assertSame(0, $rule->evaluate($context(null))->scoreContribution);
        self::assertSame(0, $rule->evaluate($context(new IpReputation()))->scoreContribution);
        self::assertSame(40, $rule->evaluate($context(new IpReputation(vpn: true, proxy: true, tor: true)))->scoreContribution);
        $hosting = $rule->evaluate($context(new IpReputation(hosting: true)));
        self::assertSame(10, $hosting->scoreContribution);
        self::assertSame(['flags' => ['hosting']], $hosting->metadata);
        self::assertSame(31, $rule->evaluate($context(new IpReputation(risk: 95)))->scoreContribution, 'provider risk / 3');
    }

    private function engine(?ConsentGateInterface $consent = null, ?IpReputationProviderInterface $reputation = null): DeviceIntelligence
    {
        $args = [
            new InMemoryDeviceRepository(),
            new InMemoryObservationRepository(),
            new InMemoryDeviceUserRepository(),
            new InMemoryTrustedDeviceRepository(),
            'risk' => RiskEngine::defaults(),
        ];
        if (null !== $consent) {
            $args['consent'] = $consent;
        }
        if (null !== $reputation) {
            $args['ipReputation'] = $reputation;
        }

        return new DeviceIntelligence(...$args);
    }
}
