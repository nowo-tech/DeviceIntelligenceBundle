<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Privacy;

use Nowo\DeviceIntelligence\Signal\SignalBag;

interface PrivacyProcessorInterface
{
    public function process(SignalBag $signals, PrivacyContext $context): SignalBag;
}
