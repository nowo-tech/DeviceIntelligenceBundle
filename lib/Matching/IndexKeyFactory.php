<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Matching;

use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligence\Signal\SignalName;

use function is_array;
use function is_string;

final class IndexKeyFactory
{
    public function fromSignals(SignalBag $signals): CandidateIndexKey
    {
        $os       = 'other';
        $platform = $signals->get(SignalName::Platform);
        if ($platform !== null) {
            $os = (string) $platform->normalizedValue;
        }
        $browser = 'other';
        $hints   = $signals->get(SignalName::ClientHints) ?? $signals->get(SignalName::UserAgent);
        if ($hints !== null) {
            $norm = $hints->normalizedValue;
            if (is_array($norm)) {
                $label = (string) ($norm['browser'] ?? 'other');
            } else {
                $label = (string) $norm;
            }
            $browser = strtolower(explode(' ', $label)[0] ?: 'other');
        }
        $gpu   = 'other';
        $webgl = $signals->get(SignalName::Webgl) ?? $signals->get(SignalName::Gpu);
        if ($webgl !== null && is_array($webgl->normalizedValue)) {
            $gpu = (string) ($webgl->normalizedValue['vendor'] ?? 'other');
        }
        $screen       = 'other';
        $screenSignal = $signals->get(SignalName::Screen);
        if ($screenSignal !== null && is_array($screenSignal->normalizedValue)) {
            $screen = (string) ($screenSignal->normalizedValue['class'] ?? 'other');
        }
        $tz       = 'UTC';
        $tzSignal = $signals->get(SignalName::Timezone);
        if ($tzSignal !== null) {
            $tz = (string) $tzSignal->normalizedValue;
        }
        $digests = [];
        foreach ([SignalName::Canvas, SignalName::Audio, SignalName::Webgl] as $name) {
            $s = $signals->get($name);
            if ($s === null) {
                continue;
            }
            if (is_string($s->normalizedValue) && $s->normalizedValue !== '') {
                $digests[$name->value] = $s->normalizedValue;
            } elseif (is_array($s->normalizedValue) && isset($s->normalizedValue['renderer'])) {
                $digests[$name->value] = (string) $s->normalizedValue['renderer'];
            }
        }

        return new CandidateIndexKey($os, $browser, $gpu, $screen, $tz, CandidateIndexKey::blockingKeyFrom($digests));
    }
}
