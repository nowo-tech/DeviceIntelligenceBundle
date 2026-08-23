<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Matching;

use Nowo\DeviceIntelligence\Signal\SignalName;

use function is_string;

final readonly class CandidateIndexKey
{
    public function __construct(
        public string $osFamily,
        public string $browserFamily,
        public string $gpuFamily,
        public string $screenClass,
        public string $timezone,
        public string $blockingKey,
    ) {
    }

    public static function unknown(): self
    {
        return new self('other', 'other', 'other', 'other', 'UTC', '');
    }

    /**
     * Coarse 16-bit prefix used only for candidate blocking, never as identity.
     *
     * @param array<string, string> $stableDigests
     */
    public static function blockingKeyFrom(array $stableDigests): string
    {
        if ($stableDigests === []) {
            return '';
        }
        ksort($stableDigests);
        $material = implode('|', $stableDigests);

        return substr(hash('sha256', $material), 0, 4);
    }

    public function digestFor(SignalName $name, mixed $normalized): ?string
    {
        unset($name);
        if (is_string($normalized) && $normalized !== '') {
            return $normalized;
        }

        return null;
    }
}
