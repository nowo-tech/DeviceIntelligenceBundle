<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Request;

use Nowo\DeviceIntelligence\Analysis;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Matching\DeviceMatch;
use Nowo\DeviceIntelligence\Matching\Similarity;
use Nowo\DeviceIntelligence\Observation\DeviceObservation;
use Nowo\DeviceIntelligence\Risk\RiskAssessment;
use Nowo\DeviceIntelligence\Risk\RiskLevel;
use Nowo\DeviceIntelligence\Risk\RiskScore;

/**
 * Builds a DeviceContext from a stored observation without re-running analyze().
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class TokenDeviceContextFactory
{
    public function fromStored(Device $device, DeviceObservation $observation, bool $trusted): DeviceContext
    {
        $score = new RiskScore($observation->riskScore);
        $level = match (true) {
            $observation->riskScore >= 90 => RiskLevel::Critical,
            $observation->riskScore >= 65 => RiskLevel::High,
            $observation->riskScore >= 30 => RiskLevel::Medium,
            default => RiskLevel::Low,
        };
        $analysis = new Analysis(
            $device,
            new DeviceMatch($device, $device->confidence, new Similarity(0.0), [], [], false),
            new RiskAssessment($score, $level, []),
            $observation,
            $observation->signals,
            $observation->degraded,
            [],
        );

        return new DeviceContext($analysis, $trusted);
    }
}
