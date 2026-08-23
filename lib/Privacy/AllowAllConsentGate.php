<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Privacy;

final class AllowAllConsentGate implements ConsentGateInterface
{
    public function allows(string $collector, ConsentContext $context): bool
    {
        return !(!$context->highEntropy && \in_array($collector, ['audio', 'canvas', 'webgl', 'fonts'], true))

        ;
    }
}
