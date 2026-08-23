<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Http;

use Nowo\DeviceIntelligence\Infrastructure\FrozenClock;
use Nowo\DeviceIntelligence\Observation\ObservationId;
use Nowo\DeviceIntelligenceBundle\Http\ObservationTokenIssuer;
use Nowo\DeviceIntelligenceBundle\Tests\Support\ProcessedConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ObservationTokenIssuerTest extends TestCase
{
    public function testIssueAndRead(): void
    {
        $now = new \DateTimeImmutable('2026-08-23T10:00:00Z');
        $id = ObservationId::generate($now);
        $issuer = new ObservationTokenIssuer(ProcessedConfig::object(), new FrozenClock($now), 'secret');
        $cookie = $issuer->issue($id, 'nonce-1', true);

        self::assertSame('di_obs', $cookie->getName());
        self::assertTrue($cookie->isHttpOnly());

        $request = Request::create('/');
        $request->cookies->set($cookie->getName(), (string) $cookie->getValue());
        $decoded = $issuer->read($request);

        self::assertNotNull($decoded);
        self::assertSame($id->value, $decoded['observation_id']);
        self::assertSame('nonce-1', $decoded['nonce']);
    }

    public function testReadEmptyAndTampered(): void
    {
        $now = new \DateTimeImmutable('2026-08-23T10:00:00Z');
        $issuer = new ObservationTokenIssuer(ProcessedConfig::object(), new FrozenClock($now), 'secret');

        self::assertNull($issuer->read(Request::create('/')));

        $request = Request::create('/');
        $request->cookies->set('di_obs', 'not-a-token');
        self::assertNull($issuer->read($request));
    }

    public function testExpiredTokenIsIgnored(): void
    {
        $now = new \DateTimeImmutable('2026-08-23T10:00:00Z');
        $id = ObservationId::generate($now);
        $issuer = new ObservationTokenIssuer(
            ProcessedConfig::object(['token_ttl' => 60]),
            new FrozenClock($now),
            'secret',
        );
        $cookie = $issuer->issue($id, 'n', false);
        $later = new ObservationTokenIssuer(
            ProcessedConfig::object(['token_ttl' => 60]),
            new FrozenClock($now->modify('+2 hours')),
            'secret',
        );
        $request = Request::create('/');
        $request->cookies->set($cookie->getName(), (string) $cookie->getValue());
        self::assertNull($later->read($request));
    }
}
