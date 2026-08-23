<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Signal\Normalizer;

use Nowo\DeviceIntelligence\Signal\Signal;
use Nowo\DeviceIntelligence\Signal\SignalName;

final class ScreenNormalizer implements SignalNormalizerInterface
{
    public function supports(SignalName $name): bool
    {
        return SignalName::Screen === $name;
    }

    public function normalize(Signal $signal): Signal
    {
        $w = 0;
        $h = 0;
        if (\is_array($signal->value)) {
            $w = (int) ($signal->value['width'] ?? $signal->value['w'] ?? 0);
            $h = (int) ($signal->value['height'] ?? $signal->value['h'] ?? 0);
        } elseif (\is_string($signal->value) && preg_match('/(\d+)\s*[x×]\s*(\d+)/', $signal->value, $m)) {
            $w = (int) $m[1];
            $h = (int) $m[2];
        }
        $max = max($w, $h);
        $class = match (true) {
            $max <= 0 => 'other',
            $max < 800 => 'mobile-s',
            $max < 1100 => 'mobile-l',
            $max < 1400 => 'tablet',
            $max < 2000 => 'hd',
            $max < 3000 => 'qhd',
            default => 'uhd',
        };

        return $signal->withNormalized([
            'class' => $class,
            'width' => $w,
            'height' => $h,
        ]);
    }
}
