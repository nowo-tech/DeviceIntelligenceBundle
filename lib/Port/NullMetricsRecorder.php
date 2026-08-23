<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Port;

final class NullMetricsRecorder implements MetricsRecorderInterface
{
    public function increment(string $name, array $tags = [], int $by = 1): void
    {
        unset($name, $tags, $by);
    }

    public function timing(string $name, float $milliseconds, array $tags = []): void
    {
        unset($name, $milliseconds, $tags);
    }
}
