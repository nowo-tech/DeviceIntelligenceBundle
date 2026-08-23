<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Privacy;

enum PrivacyMode: string
{
    case Strict = 'strict';
    case Balanced = 'balanced';
    case Full = 'full';

    /**
     * @return list<string>
     */
    public function blockedHighEntropyCollectors(): array
    {
        return match ($this) {
            self::Strict => ['audio', 'canvas', 'webgl', 'fonts'],
            self::Balanced, self::Full => [],
        };
    }
}
