<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Http;

use Nowo\DeviceIntelligenceBundle\Config\DeviceIntelligenceConfig;
use Nowo\DeviceIntelligenceBundle\Http\CollectRequestValidator;
use Nowo\DeviceIntelligenceBundle\Http\Exception\CollectValidationException;
use Nowo\DeviceIntelligenceBundle\Http\OriginValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class CollectRequestValidatorTest extends TestCase
{
    public function testRejectsStaleTimestamp(): void
    {
        $config = new DeviceIntelligenceConfig([
            'enabled' => true,
            'default_profile' => 'default',
            'profiles' => ['default' => []],
            'endpoint' => [
                'csrf' => 'none',
                'max_payload_bytes' => 65536,
                'timestamp_skew' => 300,
                'replay_protection' => false,
                'allowed_origins' => [],
            ],
            'token_cookie' => ['name' => 'di_obs'],
            'token_ttl' => 3600,
            'observe_on_every_request' => false,
            'ip_salt' => '',
        ]);
        $cache = $this->createMock(CacheInterface::class);
        $validator = new CollectRequestValidator($config, new OriginValidator($config), $cache);

        $stale = time() - 3600;
        $request = Request::create(
            '/_nowo/device-intelligence/collect',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_HOST' => 'example.test'],
            json_encode(['v' => 1, 'timestamp' => $stale, 'nonce' => 'nonce-0001', 'signals' => []], \JSON_THROW_ON_ERROR),
        );

        $this->expectException(CollectValidationException::class);
        $this->expectExceptionMessage('Stale or future timestamp');
        $validator->validate($request);
    }

    public function testAcceptsFreshTimestamp(): void
    {
        $config = new DeviceIntelligenceConfig([
            'enabled' => true,
            'default_profile' => 'default',
            'profiles' => ['default' => []],
            'endpoint' => [
                'csrf' => 'none',
                'max_payload_bytes' => 65536,
                'timestamp_skew' => 300,
                'replay_protection' => false,
                'allowed_origins' => [],
            ],
            'token_cookie' => ['name' => 'di_obs'],
            'token_ttl' => 3600,
            'observe_on_every_request' => false,
            'ip_salt' => '',
        ]);
        $cache = $this->createMock(CacheInterface::class);
        $validator = new CollectRequestValidator($config, new OriginValidator($config), $cache);
        $request = Request::create(
            '/_nowo/device-intelligence/collect',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_HOST' => 'example.test'],
            json_encode(['v' => 1, 'timestamp' => time(), 'nonce' => 'nonce-0001', 'signals' => []], \JSON_THROW_ON_ERROR),
        );

        $payload = $validator->validate($request);
        self::assertSame(1, (int) $payload['v']);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidNonceProvider(): iterable
    {
        yield 'pipe breaks token decode' => ['abcdefgh|forged|9999999999|x'];
        yield 'too short' => ['abc'];
        yield 'too long' => [str_repeat('a', 129)];
        yield 'non ascii' => ['nonce-ñññññ'];
        yield 'whitespace' => ['nonce 00001'];
        yield 'not a string' => [12345678];
        yield 'array' => [['nonce-0001']];
    }

    #[DataProvider('invalidNonceProvider')]
    public function testRejectsMalformedNonce(mixed $nonce): void
    {
        foreach ([true, false] as $replay) {
            $validator = new CollectRequestValidator(
                $config = $this->config($replay),
                new OriginValidator($config),
                $this->createMock(CacheInterface::class),
            );
            try {
                $validator->validate($this->request(['v' => 1, 'timestamp' => time(), 'nonce' => $nonce]));
                self::fail('Malformed nonce must be rejected.');
            } catch (CollectValidationException $e) {
                self::assertSame('Invalid nonce.', $e->getMessage());
                self::assertSame(400, $e->statusCode());
            }
        }
    }

    public function testAcceptsHexAndBase64UrlNonces(): void
    {
        $config = $this->config(false);
        $validator = new CollectRequestValidator($config, new OriginValidator($config), $this->createMock(CacheInterface::class));

        foreach ([bin2hex(random_bytes(16)), 'AbC_-012', str_repeat('z', 128)] as $nonce) {
            $payload = $validator->validate($this->request(['v' => 1, 'timestamp' => time(), 'nonce' => $nonce]));
            self::assertSame($nonce, $payload['nonce']);
        }
    }

    public function testMissingNonceAllowedOnlyWithoutReplayProtection(): void
    {
        $config = $this->config(false);
        $validator = new CollectRequestValidator($config, new OriginValidator($config), $this->createMock(CacheInterface::class));
        self::assertArrayNotHasKey('nonce', $validator->validate($this->request(['v' => 1, 'timestamp' => time()])));
        self::assertSame('', $validator->validate($this->request(['v' => 1, 'timestamp' => time(), 'nonce' => '']))['nonce']);

        $config = $this->config(true);
        $validator = new CollectRequestValidator($config, new OriginValidator($config), $this->createMock(CacheInterface::class));
        $this->expectException(CollectValidationException::class);
        $this->expectExceptionMessage('Missing nonce.');
        $validator->validate($this->request(['v' => 1, 'timestamp' => time()]));
    }

    private function config(bool $replay): DeviceIntelligenceConfig
    {
        return new DeviceIntelligenceConfig([
            'enabled' => true,
            'default_profile' => 'default',
            'profiles' => ['default' => []],
            'endpoint' => [
                'csrf' => 'none',
                'max_payload_bytes' => 65536,
                'timestamp_skew' => 300,
                'replay_protection' => $replay,
                'allowed_origins' => [],
            ],
            'token_cookie' => ['name' => 'di_obs'],
            'token_ttl' => 3600,
            'observe_on_every_request' => false,
            'ip_salt' => '',
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(array $payload): Request
    {
        return Request::create(
            '/_device/collect',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_HOST' => 'example.test'],
            json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }
}
