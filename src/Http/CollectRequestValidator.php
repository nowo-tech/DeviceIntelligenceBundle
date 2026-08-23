<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Http;

use JsonException;
use Nowo\DeviceIntelligenceBundle\Config\DeviceIntelligenceConfig;
use Nowo\DeviceIntelligenceBundle\Http\Exception\CollectValidationException;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\HttpFoundation\Request;

use function is_array;
use function is_string;
use function strlen;

use const JSON_THROW_ON_ERROR;

/**
 * Validates collect transport: origin, size, schema v=1, timestamp skew, nonce replay.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class CollectRequestValidator
{
    public function __construct(
        private DeviceIntelligenceConfig $config,
        private OriginValidator $origins,
        private CacheInterface $cache,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function validate(Request $request): array
    {
        $this->origins->validate($request);

        $max    = (int) $this->config->endpoint()['max_payload_bytes'];
        $length = $request->headers->get('Content-Length');
        if ($length !== null && (int) $length > $max) {
            throw new CollectValidationException('Payload exceeds max_payload_bytes.', 413);
        }

        $raw = $request->getContent();
        if (strlen($raw) > $max) {
            throw new CollectValidationException('Payload exceeds max_payload_bytes.', 413);
        }
        if ($raw === '') {
            throw new CollectValidationException('Empty JSON body.');
        }

        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new CollectValidationException('Invalid JSON: ' . $e->getMessage());
        }
        if (!is_array($payload)) {
            throw new CollectValidationException('JSON body must be an object.');
        }

        $version = $payload['v'] ?? $payload['schemaVersion'] ?? null;
        if ((int) $version !== 1) {
            throw new CollectValidationException('Unsupported schema version; expected v=1.');
        }

        $timestamp = $payload['timestamp'] ?? $payload['ts'] ?? null;
        if (!is_numeric($timestamp)) {
            throw new CollectValidationException('Missing or invalid timestamp.');
        }
        $ts = (int) $timestamp;
        if ($ts > 2_000_000_000) {
            $ts = (int) floor($ts / 1000);
        }
        $skew = (int) $this->config->endpoint()['timestamp_skew'];
        if (abs(time() - $ts) > $skew) {
            throw new CollectValidationException('Stale or future timestamp.');
        }

        $nonce = isset($payload['nonce']) && is_string($payload['nonce']) ? $payload['nonce'] : '';
        if ($this->config->endpoint()['replay_protection']) {
            if ($nonce === '') {
                throw new CollectValidationException('Missing nonce.');
            }
            $key = 'di.nonce.' . hash('sha256', $nonce);
            if ($this->cache->has($key)) {
                throw new CollectValidationException('Replay detected.', 409);
            }
            $this->cache->set($key, 1, max(60, $skew * 2));
        }

        return $payload;
    }
}
