<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Signal;

final class ClientHintPlatformBridge
{
    public static function platformFromHints(SignalBag $bag, \DateTimeImmutable $now): SignalBag
    {
        if ($bag->has(SignalName::Platform)) {
            return $bag;
        }
        $hints = $bag->get(SignalName::ClientHints);
        if (null === $hints) {
            return $bag;
        }
        $platform = null;
        if (\is_array($hints->normalizedValue) && isset($hints->normalizedValue['platform'])) {
            $platform = (string) $hints->normalizedValue['platform'];
        } elseif (\is_array($hints->value) && isset($hints->value['sec-ch-ua-platform'])) {
            $platform = trim((string) $hints->value['sec-ch-ua-platform'], '"');
        }
        if (null === $platform || '' === $platform) {
            return $bag;
        }

        return $bag->with(new Signal(
            SignalName::Platform,
            $platform,
            $platform,
            new Quality(0.8),
            SignalName::Platform->expectedStability(),
            EntropyCategory::Low,
            $now,
            SignalSource::Server,
        ));
    }
}
