<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Signal\Server;

use Nowo\DeviceIntelligence\AnalysisInput;
use Nowo\DeviceIntelligence\Signal\EntropyCategory;
use Nowo\DeviceIntelligence\Signal\Quality;
use Nowo\DeviceIntelligence\Signal\Signal;
use Nowo\DeviceIntelligence\Signal\SignalName;
use Nowo\DeviceIntelligence\Signal\SignalSource;

final class DefaultServerSignalProvider implements ServerSignalProviderInterface
{
    public function collect(AnalysisInput $input): iterable
    {
        $now = $input->now;
        if (null !== $input->userAgent && '' !== $input->userAgent) {
            yield new Signal(
                SignalName::UserAgent,
                $input->userAgent,
                $input->userAgent,
                new Quality(0.7),
                SignalName::UserAgent->expectedStability(),
                EntropyCategory::Low,
                $now,
                SignalSource::Server,
            );
        }
        $accept = $input->headers['accept-language'] ?? $input->headers['accept'] ?? null;
        if (\is_string($accept) && '' !== $accept) {
            yield new Signal(
                SignalName::AcceptHeaders,
                $accept,
                $accept,
                new Quality(0.5),
                0.4,
                EntropyCategory::Low,
                $now,
                SignalSource::Server,
            );
        }
        if (null !== $input->sessionId && '' !== $input->sessionId) {
            $hash = hash('sha256', $input->sessionId);
            yield new Signal(
                SignalName::Session,
                $hash,
                $hash,
                new Quality(1.0),
                0.3,
                EntropyCategory::Low,
                $now,
                SignalSource::Server,
            );
        }
        $hints = [];
        foreach (['sec-ch-ua', 'sec-ch-ua-mobile', 'sec-ch-ua-platform'] as $h) {
            if (isset($input->headers[$h])) {
                $hints[$h] = $input->headers[$h];
            }
        }
        if ([] !== $hints) {
            yield new Signal(
                SignalName::ClientHints,
                $hints,
                $hints,
                new Quality(0.8),
                SignalName::ClientHints->expectedStability(),
                EntropyCategory::Medium,
                $now,
                SignalSource::Server,
            );
        }
    }
}
