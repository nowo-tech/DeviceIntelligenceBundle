<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Http;

use Nowo\DeviceIntelligenceBundle\Config\DeviceIntelligenceConfig;
use Nowo\DeviceIntelligenceBundle\Http\CollectRequestValidator;
use Nowo\DeviceIntelligenceBundle\Http\Exception\CollectValidationException;
use Nowo\DeviceIntelligenceBundle\Http\OriginValidator;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\HttpFoundation\Request;

use const JSON_THROW_ON_ERROR;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class CollectRequestValidatorTest extends TestCase
{
    public function testRejectsStaleTimestamp(): void
    {
        $config = new DeviceIntelligenceConfig([
            'enabled'         => true,
            'default_profile' => 'default',
            'profiles'        => ['default' => []],
            'endpoint'        => [
                'csrf'              => 'none',
                'max_payload_bytes' => 65536,
                'timestamp_skew'    => 300,
                'replay_protection' => false,
                'allowed_origins'   => [],
            ],
            'token_cookie'             => ['name' => 'di_obs'],
            'token_ttl'                => 3600,
            'observe_on_every_request' => false,
            'ip_salt'                  => '',
        ]);
        $cache     = $this->createMock(CacheInterface::class);
        $validator = new CollectRequestValidator($config, new OriginValidator($config), $cache);

        $stale   = time() - 3600;
        $request = Request::create(
            '/_nowo/device-intelligence/collect',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_HOST' => 'example.test'],
            json_encode(['v' => 1, 'timestamp' => $stale, 'nonce' => 'n1', 'signals' => []], JSON_THROW_ON_ERROR),
        );

        $this->expectException(CollectValidationException::class);
        $this->expectExceptionMessage('Stale or future timestamp');
        $validator->validate($request);
    }

    public function testAcceptsFreshTimestamp(): void
    {
        $config = new DeviceIntelligenceConfig([
            'enabled'         => true,
            'default_profile' => 'default',
            'profiles'        => ['default' => []],
            'endpoint'        => [
                'csrf'              => 'none',
                'max_payload_bytes' => 65536,
                'timestamp_skew'    => 300,
                'replay_protection' => false,
                'allowed_origins'   => [],
            ],
            'token_cookie'             => ['name' => 'di_obs'],
            'token_ttl'                => 3600,
            'observe_on_every_request' => false,
            'ip_salt'                  => '',
        ]);
        $cache     = $this->createMock(CacheInterface::class);
        $validator = new CollectRequestValidator($config, new OriginValidator($config), $cache);
        $request   = Request::create(
            '/_nowo/device-intelligence/collect',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_HOST' => 'example.test'],
            json_encode(['v' => 1, 'timestamp' => time(), 'nonce' => 'n1', 'signals' => []], JSON_THROW_ON_ERROR),
        );

        $payload = $validator->validate($request);
        self::assertSame(1, (int) $payload['v']);
    }
}
