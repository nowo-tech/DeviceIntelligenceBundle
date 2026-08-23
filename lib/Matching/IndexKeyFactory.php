<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Matching;

use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligence\Signal\SignalName;

final class IndexKeyFactory
{
    public function fromSignals(SignalBag $signals): CandidateIndexKey
    {
        $os = 'other';
        $platform = $signals->get(SignalName::Platform);
        if (null !== $platform) {
            $os = (string) $platform->normalizedValue;
        }
        $browser = 'other';
        $hints = $signals->get(SignalName::ClientHints) ?? $signals->get(SignalName::UserAgent);
        if (null !== $hints) {
            $norm = $hints->normalizedValue;
            if (\is_array($norm)) {
                $label = (string) ($norm['browser'] ?? 'other');
            } else {
                $label = (string) $norm;
            }
            $browser = strtolower(explode(' ', $label)[0] ?: 'other');
        }
        $gpu = 'other';
        $webgl = $signals->get(SignalName::Webgl) ?? $signals->get(SignalName::Gpu);
        if (null !== $webgl && \is_array($webgl->normalizedValue)) {
            $gpu = (string) ($webgl->normalizedValue['vendor'] ?? 'other');
        }
        $screen = 'other';
        $screenSignal = $signals->get(SignalName::Screen);
        if (null !== $screenSignal && \is_array($screenSignal->normalizedValue)) {
            $screen = (string) ($screenSignal->normalizedValue['class'] ?? 'other');
        }
        $tz = 'UTC';
        $tzSignal = $signals->get(SignalName::Timezone);
        if (null !== $tzSignal) {
            $tz = (string) $tzSignal->normalizedValue;
        }
        $digests = [];
        foreach ([SignalName::Canvas, SignalName::Audio, SignalName::Webgl] as $name) {
            $s = $signals->get($name);
            if (null === $s) {
                continue;
            }
            if (\is_string($s->normalizedValue) && '' !== $s->normalizedValue) {
                $digests[$name->value] = $s->normalizedValue;
            } elseif (\is_array($s->normalizedValue) && isset($s->normalizedValue['renderer'])) {
                $digests[$name->value] = (string) $s->normalizedValue['renderer'];
            }
        }

        return new CandidateIndexKey($os, $browser, $gpu, $screen, $tz, CandidateIndexKey::blockingKeyFrom($digests));
    }
}
