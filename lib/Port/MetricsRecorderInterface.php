<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Port;

interface MetricsRecorderInterface
{
    /**
     * @param array<string, string> $tags
     */
    public function increment(string $name, array $tags = [], int $by = 1): void;

    /**
     * @param array<string, string> $tags
     */
    public function timing(string $name, float $milliseconds, array $tags = []): void;
}
