<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Privacy;

final readonly class ConsentContext
{
    public function __construct(
        public PrivacyMode $mode,
        public bool $highEntropy = true,
    ) {
    }
}
