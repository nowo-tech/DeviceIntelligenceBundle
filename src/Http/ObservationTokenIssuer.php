<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Http;

use Nowo\DeviceIntelligence\Observation\ObservationId;
use Nowo\DeviceIntelligenceBundle\Config\DeviceIntelligenceConfig;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * HMAC cookie carrying observation_id|iat|exp|nonce — never a fingerprint.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ObservationTokenIssuer
{
    public function __construct(
        private DeviceIntelligenceConfig $config,
        private ClockInterface $clock,
        private string $secret,
    ) {
    }

    public function issue(ObservationId $observationId, string $nonce, bool $secure): Cookie
    {
        $now = $this->clock->now()->getTimestamp();
        $exp = $now + $this->config->tokenTtl();
        $payload = $observationId->value.'|'.$now.'|'.$exp.'|'.$nonce;
        $value = $this->encode($payload);
        $cookie = $this->config->tokenCookie();

        $secureFlag = match ((string) $cookie['secure']) {
            '1', 'true' => true,
            '0', 'false' => false,
            default => $secure,
        };

        $sameSite = (string) $cookie['samesite'];
        if (!\in_array($sameSite, ['lax', 'strict', 'none', ''], true)) {
            $sameSite = 'lax';
        }

        return Cookie::create((string) $cookie['name'])
            ->withValue($value)
            ->withExpires($exp)
            ->withPath((string) $cookie['path'])
            ->withDomain(\is_string($cookie['domain']) && '' !== $cookie['domain'] ? $cookie['domain'] : null)
            ->withSecure($secureFlag)
            ->withHttpOnly((bool) $cookie['httponly'])
            ->withSameSite($sameSite);
    }

    /**
     * @return array{observation_id: string, iat: int, exp: int, nonce: string}|null
     */
    public function read(Request $request): ?array
    {
        $name = (string) $this->config->tokenCookie()['name'];
        $raw = (string) $request->cookies->get($name, '');
        if ('' === $raw) {
            return null;
        }
        $decoded = $this->decode($raw);
        if (null === $decoded) {
            return null;
        }
        if ($decoded['exp'] < $this->clock->now()->getTimestamp()) {
            return null;
        }

        return $decoded;
    }

    private function encode(string $payload): string
    {
        $sig = hash_hmac('sha256', $payload, $this->secret);

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=').'.'.$sig;
    }

    /**
     * @return array{observation_id: string, iat: int, exp: int, nonce: string}|null
     */
    private function decode(string $value): ?array
    {
        $parts = explode('.', $value, 2);
        if (2 !== \count($parts)) {
            return null;
        }
        [$b64, $sig] = $parts;
        $pad = \strlen($b64) % 4;
        if (0 !== $pad) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $payload = base64_decode(strtr($b64, '-_', '+/'), true);
        if (!\is_string($payload) || !hash_equals(hash_hmac('sha256', $payload, $this->secret), $sig)) {
            return null;
        }
        $chunks = explode('|', $payload);
        if (4 !== \count($chunks)) {
            return null;
        }

        return [
            'observation_id' => $chunks[0],
            'iat' => (int) $chunks[1],
            'exp' => (int) $chunks[2],
            'nonce' => $chunks[3],
        ];
    }
}
