<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Privacy;

interface ConsentGateInterface
{
    public function allows(string $collector, ConsentContext $context): bool;
}
