<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Device;

use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligence\Signal\SignalName;

final class DefaultDeviceLabeler implements DeviceLabelerInterface
{
    public function label(SignalBag $signals): string
    {
        $browser = 'Browser';
        $os = 'unknown OS';
        $hints = $signals->get(SignalName::ClientHints) ?? $signals->get(SignalName::UserAgent);
        if (null !== $hints) {
            $norm = $hints->normalizedValue;
            $browser = \is_array($norm) ? (string) ($norm['browser'] ?? $browser) : (string) $norm;
        }
        $platform = $signals->get(SignalName::Platform);
        if (null !== $platform) {
            $os = (string) $platform->normalizedValue;
        } elseif (null !== $hints && \is_array($hints->normalizedValue)) {
            $os = (string) ($hints->normalizedValue['platform'] ?? $os);
        }
        $browser = '' !== $browser ? $browser : 'Browser';
        $os = '' !== $os ? $os : 'unknown OS';

        return \sprintf('%s on %s', $browser, $os);
    }
}
