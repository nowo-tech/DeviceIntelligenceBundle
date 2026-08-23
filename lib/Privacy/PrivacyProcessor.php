<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Privacy;

use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligence\Signal\SignalName;

final class PrivacyProcessor implements PrivacyProcessorInterface
{
    public function process(SignalBag $signals, PrivacyContext $context): SignalBag
    {
        $blocked = $context->mode->blockedHighEntropyCollectors();
        if (!$context->highEntropyConsent) {
            $blocked = array_unique([...$blocked, 'audio', 'canvas', 'webgl', 'fonts']);
        }
        foreach ($blocked as $name) {
            $enum = SignalName::tryFrom($name);
            if (null !== $enum) {
                $signals = $signals->without($enum);
            }
        }
        if (!$context->storeUserAgent) {
            $signals = $signals->without(SignalName::UserAgent);
        }

        return $signals;
    }
}
