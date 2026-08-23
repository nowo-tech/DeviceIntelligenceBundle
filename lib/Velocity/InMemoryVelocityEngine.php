<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Velocity;

use Nowo\DeviceIntelligence\Device\Device;

/**
 * In-process velocity (tests / single worker). Not shared across FrankenPHP workers.
 */
final class InMemoryVelocityEngine implements VelocityEngineInterface
{
    /** @var array<string, list<int>> */
    private array $hits = [];

    public function increment(string $key, Device $device, int $by = 1): void
    {
        $k = $key.'.'.$device->id->value;
        for ($i = 0; $i < $by; ++$i) {
            $this->hits[$k][] = time();
        }
    }

    public function count(string $key, Device $device, TimeWindow $window): int
    {
        $k = $key.'.'.$device->id->value;
        $cutoff = time() - $window->seconds();
        $n = 0;
        foreach ($this->hits[$k] ?? [] as $ts) {
            if ($ts >= $cutoff) {
                ++$n;
            }
        }

        return $n;
    }
}
