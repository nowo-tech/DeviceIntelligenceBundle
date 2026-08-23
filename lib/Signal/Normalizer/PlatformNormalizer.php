<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Signal\Normalizer;

use Nowo\DeviceIntelligence\Signal\Signal;
use Nowo\DeviceIntelligence\Signal\SignalName;

final class PlatformNormalizer implements SignalNormalizerInterface
{
    public function supports(SignalName $name): bool
    {
        return SignalName::Platform === $name;
    }

    public function normalize(Signal $signal): Signal
    {
        $raw = strtolower((string) $signal->value);
        $family = 'other';
        foreach (['windows', 'macos', 'mac os', 'macintel', 'iphone', 'ipad', 'ios', 'android', 'linux', 'chrome os', 'cros'] as $needle) {
            if (str_contains($raw, $needle)) {
                $family = match ($needle) {
                    'mac os', 'macintel' => 'macos',
                    'iphone', 'ipad' => 'ios',
                    'chrome os', 'cros' => 'chromeos',
                    default => $needle,
                };
                break;
            }
        }

        return $signal->withNormalized($family);
    }
}
