<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Matching;

use Nowo\DeviceIntelligence\Exception\InvalidValueException;
use Nowo\DeviceIntelligence\Signal\SignalName;

/**
 * Identity weights. Must sum to ~1.0. Automation/IP/language are excluded.
 */
final readonly class MatchingWeights
{
    /**
     * @param array<string, float> $weights
     */
    public function __construct(public array $weights)
    {
        $sum = 0.0;
        foreach ($weights as $name => $weight) {
            if (!is_finite($weight) || $weight < 0.0 || $weight > 1.0) {
                throw new InvalidValueException(\sprintf('Weight for "%s" must be in [0, 1].', $name));
            }
            $sum += $weight;
        }
        if (abs($sum - 1.0) > 0.001) {
            throw new InvalidValueException(\sprintf('Matching weights must sum to 1.0, got %s.', (string) $sum));
        }
    }

    public static function defaults(): self
    {
        return new self([
            SignalName::Audio->value => 0.12,
            SignalName::Canvas->value => 0.18,
            SignalName::Webgl->value => 0.20,
            SignalName::Platform->value => 0.10,
            SignalName::Screen->value => 0.08,
            SignalName::Timezone->value => 0.05,
            'hardware' => 0.07,
            SignalName::BrowserCapabilities->value => 0.10,
            SignalName::ClientHints->value => 0.10,
        ]);
    }

    public function weightFor(SignalName $name): float
    {
        if (isset($this->weights[$name->value])) {
            return $this->weights[$name->value];
        }
        if (SignalName::HardwareConcurrency === $name || SignalName::DeviceMemory === $name) {
            return $this->weights['hardware'] ?? 0.0;
        }
        if (SignalName::Gpu === $name) {
            return $this->weights[SignalName::Webgl->value] ?? 0.0;
        }

        return 0.0;
    }
}
