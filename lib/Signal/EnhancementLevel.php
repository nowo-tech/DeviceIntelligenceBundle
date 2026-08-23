<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Signal;

final class EnhancementLevel
{
    public static function of(SignalBag $bag): int
    {
        if ($bag->has(SignalName::Audio)) {
            return 3;
        }
        if ($bag->has(SignalName::Canvas) || $bag->has(SignalName::Webgl)) {
            return 2;
        }
        if ($bag->has(SignalName::Platform) || $bag->has(SignalName::ClientHints)) {
            return 1;
        }

        return 0;
    }
}
