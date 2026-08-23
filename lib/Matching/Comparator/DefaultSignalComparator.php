<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Matching\Comparator;

use Nowo\DeviceIntelligence\Signal\Signal;
use Nowo\DeviceIntelligence\Signal\SignalName;

final class DefaultSignalComparator implements SignalComparatorInterface
{
    public function similarity(?Signal $incoming, ?Signal $stored): float
    {
        if (null === $incoming || null === $stored) {
            return -1.0; // skip
        }

        return match ($incoming->name) {
            SignalName::Platform => $this->platform($incoming, $stored),
            SignalName::Screen => $this->screen($incoming, $stored),
            SignalName::Timezone => $this->timezone($incoming, $stored),
            SignalName::Webgl, SignalName::Gpu => $this->webgl($incoming, $stored),
            SignalName::Canvas, SignalName::Audio, SignalName::Fonts => $this->digest($incoming, $stored),
            SignalName::HardwareConcurrency, SignalName::DeviceMemory => $this->numeric($incoming, $stored),
            SignalName::BrowserCapabilities => $this->jaccard($incoming, $stored),
            SignalName::ClientHints, SignalName::UserAgent => $this->browser($incoming, $stored),
            default => json_encode($incoming->normalizedValue) === json_encode($stored->normalizedValue) ? 1.0 : 0.0,
        };
    }

    private function platform(Signal $a, Signal $b): float
    {
        return (string) $a->normalizedValue === (string) $b->normalizedValue ? 1.0 : 0.0;
    }

    private function timezone(Signal $a, Signal $b): float
    {
        return (string) $a->normalizedValue === (string) $b->normalizedValue ? 1.0 : 0.35;
    }

    private function digest(Signal $a, Signal $b): float
    {
        return (string) $a->normalizedValue === (string) $b->normalizedValue ? 1.0 : 0.0;
    }

    private function numeric(Signal $a, Signal $b): float
    {
        $left = (int) $a->normalizedValue;
        $right = (int) $b->normalizedValue;
        $delta = abs($left - $right);

        return match (true) {
            0 === $delta => 1.0,
            1 === $delta => 0.7,
            2 === $delta => 0.4,
            default => 0.15,
        };
    }

    private function screen(Signal $a, Signal $b): float
    {
        $ca = \is_array($a->normalizedValue) ? (string) ($a->normalizedValue['class'] ?? '') : (string) $a->normalizedValue;
        $cb = \is_array($b->normalizedValue) ? (string) ($b->normalizedValue['class'] ?? '') : (string) $b->normalizedValue;
        if ($ca === $cb) {
            return 1.0;
        }

        return 0.15;
    }

    private function webgl(Signal $a, Signal $b): float
    {
        $va = \is_array($a->normalizedValue) ? (string) ($a->normalizedValue['vendor'] ?? '') : (string) $a->normalizedValue;
        $vb = \is_array($b->normalizedValue) ? (string) ($b->normalizedValue['vendor'] ?? '') : (string) $b->normalizedValue;
        $ra = \is_array($a->normalizedValue) ? (string) ($a->normalizedValue['renderer'] ?? '') : '';
        $rb = \is_array($b->normalizedValue) ? (string) ($b->normalizedValue['renderer'] ?? '') : '';
        if ($va === $vb && $ra === $rb) {
            return 1.0;
        }
        if ($va === $vb) {
            return 0.7;
        }

        return 0.0;
    }

    private function jaccard(Signal $a, Signal $b): float
    {
        $left = $this->stringSet($a->normalizedValue);
        $right = $this->stringSet($b->normalizedValue);
        if ([] === $left && [] === $right) {
            return 1.0;
        }
        $inter = \count(array_intersect($left, $right));
        $union = \count(array_unique([...$left, ...$right]));

        return $union > 0 ? $inter / $union : 0.0;
    }

    /**
     * Hierarchical: Chrome 143 vs Chrome 144 → 0.9.
     */
    private function browser(Signal $a, Signal $b): float
    {
        $left = $this->browserLabel($a->normalizedValue);
        $right = $this->browserLabel($b->normalizedValue);
        if ($left === $right) {
            return 1.0;
        }
        if (preg_match('/^([A-Za-z]+)\s+(\d+)$/', $left, $m1) && preg_match('/^([A-Za-z]+)\s+(\d+)$/', $right, $m2)) {
            if ($m1[1] === $m2[1]) {
                $delta = abs((int) $m1[2] - (int) $m2[2]);

                return $delta <= 2 ? 0.9 : 0.55;
            }

            return 0.2;
        }

        return 0.0;
    }

    private function browserLabel(mixed $value): string
    {
        if (\is_array($value)) {
            return (string) ($value['browser'] ?? '');
        }

        return (string) $value;
    }

    /**
     * @return list<string>
     */
    private function stringSet(mixed $value): array
    {
        if (\is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                if (\is_bool($v)) {
                    if ($v) {
                        $out[] = (string) $k;
                    }
                    continue;
                }
                $out[] = \is_string($k) ? $k.':'.(string) $v : (string) $v;
            }

            return $out;
        }

        return '' === (string) $value ? [] : [(string) $value];
    }
}
