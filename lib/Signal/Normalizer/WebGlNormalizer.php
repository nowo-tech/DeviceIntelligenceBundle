<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Signal\Normalizer;

use Nowo\DeviceIntelligence\Signal\Signal;
use Nowo\DeviceIntelligence\Signal\SignalName;

use function is_array;

final class WebGlNormalizer implements SignalNormalizerInterface
{
    public function supports(SignalName $name): bool
    {
        return $name === SignalName::Webgl || $name === SignalName::Gpu;
    }

    public function normalize(Signal $signal): Signal
    {
        $value = $signal->value;
        if (!is_array($value)) {
            $raw = strtolower((string) $value);

            return $signal->withNormalized([
                'vendor'   => $this->vendorFamily($raw),
                'renderer' => $this->rendererFamily($raw),
            ]);
        }

        $vendor   = strtolower((string) ($value['vendor'] ?? ''));
        $renderer = strtolower((string) ($value['renderer'] ?? ''));

        return $signal->withNormalized([
            'vendor'     => $this->vendorFamily($vendor !== '' ? $vendor : $renderer),
            'renderer'   => $this->rendererFamily($renderer !== '' ? $renderer : $vendor),
            'limitsHash' => isset($value['limitsHash']) ? (string) $value['limitsHash'] : null,
        ]);
    }

    private function vendorFamily(string $raw): string
    {
        return match (true) {
            str_contains($raw, 'apple')                                                             => 'apple',
            str_contains($raw, 'nvidia') || str_contains($raw, 'geforce')                           => 'nvidia',
            str_contains($raw, 'amd') || str_contains($raw, 'radeon') || str_contains($raw, 'ati')  => 'amd',
            str_contains($raw, 'intel')                                                             => 'intel',
            str_contains($raw, 'arm') || str_contains($raw, 'mali') || str_contains($raw, 'adreno') => 'arm',
            str_contains($raw, 'google') || str_contains($raw, 'swiftshader')                       => 'software',
            default                                                                                 => 'other',
        };
    }

    private function rendererFamily(string $raw): string
    {
        $vendor = $this->vendorFamily($raw);
        if ($vendor !== 'other') {
            return $vendor . '-gpu';
        }

        return 'other';
    }
}
