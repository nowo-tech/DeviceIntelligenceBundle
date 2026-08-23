<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Signal\Normalizer;

use Nowo\DeviceIntelligence\Signal\Quality;
use Nowo\DeviceIntelligence\Signal\Signal;
use Nowo\DeviceIntelligence\Signal\SignalName;

/**
 * Keep compact hex digest; drop anything that looks like raw samples.
 */
final class CompactDigestNormalizer implements SignalNormalizerInterface
{
    public function supports(SignalName $name): bool
    {
        return SignalName::Canvas === $name || SignalName::Audio === $name || SignalName::Fonts === $name;
    }

    public function normalize(Signal $signal): Signal
    {
        $raw = $signal->value;
        if (\is_array($raw)) {
            $raw = $raw['digest'] ?? $raw['value'] ?? '';
        }
        $digest = strtolower((string) $raw);
        $digest = preg_replace('/[^a-f0-9]/', '', $digest) ?? '';
        if (\strlen($digest) > 64) {
            $digest = substr($digest, 0, 16);
        }
        $quality = $signal->quality;
        if (\strlen($digest) < 8) {
            $quality = new Quality(min($quality->value, 0.2));
        }

        return $signal->withNormalized($digest, $quality);
    }
}
