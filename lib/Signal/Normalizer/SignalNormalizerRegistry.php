<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Signal\Normalizer;

use Nowo\DeviceIntelligence\Signal\Signal;

final class SignalNormalizerRegistry
{
    /**
     * @param list<SignalNormalizerInterface> $normalizers
     */
    public function __construct(private array $normalizers)
    {
    }

    public static function defaults(): self
    {
        return new self([
            new PlatformNormalizer(),
            new BrowserVersionNormalizer(),
            new ScreenNormalizer(),
            new TimezoneNormalizer(),
            new WebGlNormalizer(),
            new CompactDigestNormalizer(),
            new IdentityNormalizer(),
        ]);
    }

    public function normalize(Signal $signal): Signal
    {
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer->supports($signal->name)) {
                return $normalizer->normalize($signal);
            }
        }

        return $signal;
    }
}
