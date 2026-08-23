<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Signal\Normalizer;

use Nowo\DeviceIntelligence\Signal\Signal;
use Nowo\DeviceIntelligence\Signal\SignalName;

use function is_array;
use function is_float;
use function is_int;
use function is_string;

/**
 * Chrome 143.0.7312.58 → Chrome 143.
 */
final class BrowserVersionNormalizer implements SignalNormalizerInterface
{
    public function supports(SignalName $name): bool
    {
        return $name === SignalName::UserAgent || $name === SignalName::ClientHints;
    }

    public function normalize(Signal $signal): Signal
    {
        if ($signal->name === SignalName::ClientHints && is_array($signal->value)) {
            /** @var array<string, mixed> $value */
            $value      = $signal->value;
            $brands     = $value['brands'] ?? $value['fullVersionList'] ?? null;
            $family     = $this->brandFamily($brands);
            $major      = $this->majorFrom($value['uaFullVersion'] ?? $value['fullVersion'] ?? $family['version'] ?? null);
            $platform   = strtolower((string) ($value['platform'] ?? $value['platform'] ?? 'other'));
            $normalized = [
                'browser'  => $family['name'] !== '' ? $family['name'] . ($major ? ' ' . $major : '') : 'other',
                'platform' => $this->platformFamily($platform),
                'mobile'   => (bool) ($value['mobile'] ?? false),
            ];

            return $signal->withNormalized($normalized);
        }

        $raw   = (string) $signal->value;
        $name  = 'other';
        $major = null;
        if (preg_match('/(Edg|Edge|OPR|Opera|Firefox|Chrome|Safari|SamsungBrowser)\/(\d+)/', $raw, $m)) {
            $name = match ($m[1]) {
                'Edg', 'Edge'    => 'Edge',
                'OPR', 'Opera'   => 'Opera',
                'SamsungBrowser' => 'Samsung',
                default          => $m[1],
            };
            $major = $m[2];
        }

        return $signal->withNormalized($name === 'other' ? 'other' : $name . ($major ? ' ' . $major : ''));
    }

    /**
     * @return array{name: string, version: ?string}
     */
    private function brandFamily(mixed $brands): array
    {
        if (!is_array($brands)) {
            return ['name' => '', 'version' => null];
        }
        foreach ($brands as $brand) {
            if (!is_array($brand)) {
                continue;
            }
            $b = (string) ($brand['brand'] ?? '');
            if (str_contains($b, 'Not') || str_contains($b, 'Brand')) {
                continue;
            }
            if (preg_match('/Chrome|Chromium|Edge|Firefox|Safari|Opera/i', $b, $m)) {
                return ['name' => ucfirst(strtolower($m[0])), 'version' => isset($brand['version']) ? (string) $brand['version'] : null];
            }
        }

        return ['name' => 'other', 'version' => null];
    }

    private function majorFrom(mixed $version): ?string
    {
        if (!is_string($version) && !is_int($version) && !is_float($version)) {
            return null;
        }
        if (preg_match('/^(\d+)/', (string) $version, $m)) {
            return $m[1];
        }

        return null;
    }

    private function platformFamily(string $platform): string
    {
        $platform = strtolower($platform);

        return match (true) {
            str_contains($platform, 'win')                                                                     => 'windows',
            str_contains($platform, 'mac')                                                                     => 'macos',
            str_contains($platform, 'iphone'), str_contains($platform, 'ipad'), str_contains($platform, 'ios') => 'ios',
            str_contains($platform, 'android')                                                                 => 'android',
            str_contains($platform, 'linux')                                                                   => 'linux',
            default                                                                                            => 'other',
        };
    }
}
