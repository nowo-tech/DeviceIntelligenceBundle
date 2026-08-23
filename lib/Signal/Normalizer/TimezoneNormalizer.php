<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Signal\Normalizer;

use Nowo\DeviceIntelligence\Signal\Signal;
use Nowo\DeviceIntelligence\Signal\SignalName;

final class TimezoneNormalizer implements SignalNormalizerInterface
{
    public function supports(SignalName $name): bool
    {
        return SignalName::Timezone === $name;
    }

    public function normalize(Signal $signal): Signal
    {
        $tz = \is_array($signal->value)
            ? (string) ($signal->value['id'] ?? $signal->value['timezone'] ?? '')
            : (string) $signal->value;

        $tz = '' !== $tz ? $tz : 'UTC';

        return $signal->withNormalized($tz);
    }
}
