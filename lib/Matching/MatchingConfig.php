<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Matching;

final readonly class MatchingConfig
{
    public function __construct(
        public MatchingWeights $weights = new MatchingWeights([
            'audio'                => 0.12,
            'canvas'               => 0.18,
            'webgl'                => 0.20,
            'platform'             => 0.10,
            'screen'               => 0.08,
            'timezone'             => 0.05,
            'hardware'             => 0.07,
            'browser_capabilities' => 0.10,
            'client_hints'         => 0.10,
        ]),
        public float $minimumConfidence = 0.75,
        public int $candidateLimit = 64,
        public string $onLowConfidence = 'new_device',
        public string $lookback = 'P180D',
    ) {
    }

    public static function defaults(): self
    {
        return new self();
    }
}
