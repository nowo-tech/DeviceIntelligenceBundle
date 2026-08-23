<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Matching;

use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Matching\Comparator\DefaultSignalComparator;
use Nowo\DeviceIntelligence\Matching\Comparator\SignalComparatorInterface;
use Nowo\DeviceIntelligence\Observation\DeviceObservation;
use Nowo\DeviceIntelligence\Signal\SignalName;

final class WeightedDeviceMatcher implements DeviceMatcherInterface
{
    public function __construct(
        private MatchingConfig $config = new MatchingConfig(),
        private SignalComparatorInterface $comparator = new DefaultSignalComparator(),
    ) {
    }

    public function match(DeviceObservation $observation, iterable $candidates): DeviceMatch
    {
        $ranked = [];
        foreach ($candidates as $device) {
            $ranked[] = $this->score($observation, $device);
        }
        usort($ranked, static fn (array $a, array $b): int => $b['similarity'] <=> $a['similarity']);

        if ([] === $ranked) {
            return new DeviceMatch(null, new Confidence(0.5), new Similarity(0.0), [], [], true);
        }

        $best = $ranked[0];
        $secondSimilarity = $ranked[1]['similarity'] ?? 0.0;
        $confidence = $this->confidence($best, $secondSimilarity, $observation);
        $hard = $best['hard'];

        $attach = false;
        if (!$hard && $confidence >= $this->config->minimumConfidence) {
            $attach = true;
        } elseif (!$hard && $confidence >= 0.55 && ($best['similarity'] - $secondSimilarity) >= 0.08) {
            $attach = true;
        }

        if (!$attach) {
            $newConfidence = Confidence::clamp(1.0 - $confidence);

            return new DeviceMatch(
                null,
                $newConfidence,
                new Similarity($best['similarity']),
                $best['changed'],
                $best['stable'],
                true,
            );
        }

        return new DeviceMatch(
            $best['device'],
            new Confidence($confidence),
            new Similarity($best['similarity']),
            $best['changed'],
            $best['stable'],
            false,
        );
    }

    /**
     * @return array{device: Device, similarity: float, coverage: float, quality: float, hard: bool, changed: list<SignalName>, stable: list<SignalName>}
     */
    private function score(DeviceObservation $observation, Device $device): array
    {
        $num = 0.0;
        $den = 0.0;
        $qualitySum = 0.0;
        $qualityN = 0;
        $highExpected = 0;
        $highPresent = 0;
        $hard = false;
        $changed = [];
        $stable = [];

        foreach (SignalName::cases() as $name) {
            if (!$name->isIdentityFeature()) {
                continue;
            }
            $weight = $this->config->weights->weightFor($name);
            if ($weight <= 0.0) {
                continue;
            }
            if ($name->isHighEntropy()) {
                ++$highExpected;
            }
            $incoming = $observation->signals->get($name);
            $stored = $device->lastSignals->get($name);
            if (null === $incoming) {
                if (null !== $stored && $stored->stability >= 0.8 && $observation->enhancementLevel >= 2) {
                    $num += $weight * 0.15 * 0.85;
                    $den += $weight * 0.85;
                    $changed[] = $name;
                }
                continue;
            }
            if ($name->isHighEntropy()) {
                ++$highPresent;
            }
            $s = $this->comparator->similarity($incoming, $stored);
            if ($s < 0.0 || null === $stored) {
                continue;
            }
            $q = min($incoming->quality->value, $stored->quality->value);
            $num += $weight * $q * $s;
            $den += $weight * $q;
            $qualitySum += $q;
            ++$qualityN;
            if ($s < 0.7) {
                $changed[] = $name;
            } else {
                $stable[] = $name;
            }
            if (SignalName::Platform === $name && $s < 0.01) {
                $hard = true;
            }
            if ($name->isHighEntropy() && $s < 0.01 && $q >= 0.8) {
                $hard = $hard || $this->countHighEntropyMismatches($observation, $device) >= 2;
            }
        }

        $similarity = $den > 0.0 ? $num / $den : 0.0;
        $coverage = $highExpected > 0 ? $highPresent / $highExpected : 1.0;
        $qualityMean = $qualityN > 0 ? $qualitySum / $qualityN : 0.5;

        return [
            'device' => $device,
            'similarity' => $similarity,
            'coverage' => $coverage,
            'quality' => $qualityMean,
            'hard' => $hard,
            'changed' => $changed,
            'stable' => $stable,
        ];
    }

    /**
     * @param array{device: Device, similarity: float, coverage: float, quality: float, hard: bool, changed: list<SignalName>, stable: list<SignalName>} $best
     */
    private function confidence(array $best, float $secondSimilarity, DeviceObservation $observation): float
    {
        $delta = $best['similarity'] - $secondSimilarity;
        $ambiguity = max(0.0, min(1.0, $delta / 0.12));
        $stability = $best['device']->stability();
        if ($best['device']->observationCount < 3) {
            $stability = 0.5;
        }
        $contradict = $best['hard'] ? 0.35 : 1.0;

        $value = $best['similarity']
            * (0.45 + 0.55 * $best['coverage'])
            * (0.75 + 0.25 * $best['quality'])
            * (0.80 + 0.20 * $stability)
            * (0.55 + 0.45 * $ambiguity)
            * $contradict;

        unset($observation);

        return max(0.0, min(1.0, $value));
    }

    private function countHighEntropyMismatches(DeviceObservation $observation, Device $device): int
    {
        $n = 0;
        foreach ([SignalName::Canvas, SignalName::Audio, SignalName::Webgl] as $name) {
            $incoming = $observation->signals->get($name);
            $stored = $device->lastSignals->get($name);
            if (null === $incoming || null === $stored) {
                continue;
            }
            if ($incoming->quality->value < 0.8 || $stored->quality->value < 0.8) {
                continue;
            }
            if ($this->comparator->similarity($incoming, $stored) < 0.01) {
                ++$n;
            }
        }

        return $n;
    }
}
