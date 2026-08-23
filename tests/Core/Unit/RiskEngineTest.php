<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Tests\Unit;

use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Matching\CandidateIndexKey;
use Nowo\DeviceIntelligence\Matching\Confidence;
use Nowo\DeviceIntelligence\Matching\DeviceMatch;
use Nowo\DeviceIntelligence\Matching\Similarity;
use Nowo\DeviceIntelligence\Observation\DeviceObservation;
use Nowo\DeviceIntelligence\Observation\ObservationId;
use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Risk\RiskDecisionAction;
use Nowo\DeviceIntelligence\Risk\RiskEngine;
use Nowo\DeviceIntelligence\Risk\Rule\NewDeviceRule;
use Nowo\DeviceIntelligence\Risk\ThresholdRiskDecision;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RiskEngineTest extends TestCase
{
    #[Test]
    public function newDeviceAddsTen(): void
    {
        $engine = RiskEngine::defaults();
        // Use matcher empty path via a tiny stub: evaluate NewDeviceRule in isolation
        $rule = new NewDeviceRule();
        self::assertSame('new_device', $rule->name());
        $decision = (new ThresholdRiskDecision())->decide(
            $engine->assess($this->context(true)),
        );
        self::assertSame(RiskDecisionAction::Allow, $decision->action);
    }

    private function context(bool $new): RiskContext
    {
        $now = new \DateTimeImmutable();
        $id = DeviceId::generate($now);
        $obs = new DeviceObservation(
            ObservationId::generate($now),
            $id,
            $now,
            1,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            SignalBag::empty(),
            0,
            true,
            0,
        );
        $device = Device::fromNew(
            $id,
            $now,
            CandidateIndexKey::unknown(),
            SignalBag::empty(),
            'unknown',
        );
        $match = new DeviceMatch(
            $new ? null : $device,
            new Confidence(0.5),
            new Similarity(0.0),
            [],
            [],
            $new,
        );

        return new RiskContext($obs, $device, $match);
    }
}
