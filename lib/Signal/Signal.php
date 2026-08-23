<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Signal;

/**
 * One device characteristic. `value` is already a compact derived representation.
 */
final readonly class Signal
{
    /**
     * @param mixed $value Compact derived value (digest, enum, short scalar/array). Never raw pixels or PCM.
     */
    public function __construct(
        public SignalName $name,
        public mixed $value,
        public mixed $normalizedValue,
        public Quality $quality,
        public float $stability,
        public EntropyCategory $entropyCategory,
        public \DateTimeImmutable $collectedAt,
        public SignalSource $source = SignalSource::Client,
        public ?string $qualityReason = null,
    ) {
    }

    public function withNormalized(mixed $normalizedValue, ?Quality $quality = null): self
    {
        return new self(
            $this->name,
            $this->value,
            $normalizedValue,
            $quality ?? $this->quality,
            $this->stability,
            $this->entropyCategory,
            $this->collectedAt,
            $this->source,
            $this->qualityReason,
        );
    }

    /**
     * Truncated summary safe for profiler and logs.
     */
    public function summary(int $max = 48): string
    {
        $encoded = \is_scalar($this->normalizedValue)
            ? (string) $this->normalizedValue
            : (json_encode($this->normalizedValue, \JSON_UNESCAPED_SLASHES) ?: '');

        if (\strlen($encoded) <= $max) {
            return $encoded;
        }

        return substr($encoded, 0, $max).'…';
    }
}
