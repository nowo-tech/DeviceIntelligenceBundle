<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Signal;

final class SignalFactory
{
    /**
     * @param array<string, mixed> $payload client `signals` map
     */
    public static function bagFromClient(array $payload, \DateTimeImmutable $now): SignalBag
    {
        $bag = SignalBag::empty();
        foreach ($payload as $name => $raw) {
            if (!\is_string($name)) {
                continue;
            }
            $enum = SignalName::tryFrom($name);
            if (null === $enum) {
                continue;
            }
            $value = $raw;
            $quality = 1.0;
            $collectedAt = $now;
            if (\is_array($raw) && \array_key_exists('value', $raw)) {
                $value = $raw['value'];
                $quality = isset($raw['quality']) ? (float) $raw['quality'] : 1.0;
                if (isset($raw['collectedAt'])) {
                    $ts = $raw['collectedAt'];
                    if (is_numeric($ts)) {
                        $collectedAt = (new \DateTimeImmutable())->setTimestamp((int) ((int) $ts > 2_000_000_000 ? ((int) $ts / 1000) : $ts));
                    }
                }
            }
            $quality = max(0.0, min(1.0, $quality));
            $bag = $bag->with(new Signal(
                $enum,
                $value,
                $value,
                new Quality($quality),
                $enum->expectedStability(),
                $enum->entropyCategory(),
                $collectedAt,
                SignalSource::Client,
            ));
        }

        return $bag;
    }
}
