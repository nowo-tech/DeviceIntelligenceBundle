<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Privacy;

final readonly class PrivacyContext
{
    public function __construct(
        public PrivacyMode $mode,
        public bool $highEntropyConsent = true,
        public bool $hashIp = true,
        public bool $storeRawIp = false,
        public bool $storeUserAgent = true,
    ) {
    }
}
