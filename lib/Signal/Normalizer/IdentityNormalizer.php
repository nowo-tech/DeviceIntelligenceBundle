<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Signal\Normalizer;

use Nowo\DeviceIntelligence\Signal\Signal;
use Nowo\DeviceIntelligence\Signal\SignalName;

/**
 * Fallback: copy value to normalizedValue.
 */
final class IdentityNormalizer implements SignalNormalizerInterface
{
    public function supports(SignalName $name): bool
    {
        unset($name);

        return true;
    }

    public function normalize(Signal $signal): Signal
    {
        if (null !== $signal->normalizedValue && $signal->normalizedValue !== $signal->value) {
            return $signal;
        }

        return $signal->withNormalized($signal->value);
    }
}
