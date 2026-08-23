<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Http;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Infrastructure\FrozenClock;
use Nowo\DeviceIntelligence\Privacy\PrivacyContext;
use Nowo\DeviceIntelligence\Privacy\PrivacyMode;
use Nowo\DeviceIntelligenceBundle\Http\AnalysisInputFactory;
use Nowo\DeviceIntelligenceBundle\Http\CollectRequestValidator;
use Nowo\DeviceIntelligenceBundle\Http\Exception\CollectValidationException;
use Nowo\DeviceIntelligenceBundle\Http\OriginValidator;
use Nowo\DeviceIntelligenceBundle\Tests\Support\ProcessedConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

use const JSON_THROW_ON_ERROR;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class CollectValidatorAndFactoryTest extends TestCase
{
    public function testValidatorHappyPathAndReplay(): void
    {
        $config    = ProcessedConfig::object(['endpoint' => ['csrf' => 'none']]);
        $cache     = new Psr16Cache(new ArrayAdapter());
        $validator = new CollectRequestValidator($config, new OriginValidator($config), $cache);
        $now       = time();
        $body      = json_encode([
            'v'         => 1,
            'timestamp' => $now,
            'nonce'     => 'once',
            'signals'   => [],
        ], JSON_THROW_ON_ERROR);
        $request = Request::create('/_device/collect', 'POST', content: $body);
        $payload = $validator->validate($request);
        self::assertSame(1, $payload['v']);

        $this->expectException(CollectValidationException::class);
        $validator->validate(Request::create('/_device/collect', 'POST', content: $body));
    }

    public function testValidatorRejectsEmptyUnsupportedAndOversized(): void
    {
        $config    = ProcessedConfig::object(['endpoint' => ['csrf' => 'none', 'max_payload_bytes' => 1024]]);
        $validator = new CollectRequestValidator($config, new OriginValidator($config), new Psr16Cache(new ArrayAdapter()));

        try {
            $validator->validate(Request::create('/_device/collect', 'POST', content: ''));
            self::fail('empty');
        } catch (CollectValidationException $e) {
            self::assertSame(400, $e->statusCode());
        }

        try {
            $validator->validate(Request::create('/_device/collect', 'POST', content: '{"v":2,"timestamp":1,"nonce":"x"}'));
            self::fail('version');
        } catch (CollectValidationException) {
            $this->addToAssertionCount(1);
        }

        $request = Request::create('/_device/collect', 'POST', content: '{"v":1}');
        $request->headers->set('Content-Length', '99999');
        try {
            $validator->validate($request);
            self::fail('size');
        } catch (CollectValidationException $e) {
            self::assertSame(413, $e->statusCode());
        }
    }

    public function testAnalysisInputFactoryMapsPayload(): void
    {
        $config  = ProcessedConfig::object();
        $now     = new DateTimeImmutable('2026-08-23T12:00:00Z');
        $factory = new AnalysisInputFactory($config, new FrozenClock($now), new PrivacyContext(PrivacyMode::Balanced), 'secret');
        $request = Request::create('/_device/collect', 'POST', server: [
            'REMOTE_ADDR'     => '203.0.113.9',
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
        ]);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $input = $factory->fromRequest($request, [
            'v'          => 1,
            'sdkVersion' => '1.0.0',
            'nonce'      => 'n1',
            'consent'    => ['highEntropy' => false],
            'signals'    => [
                'timezone' => ['value' => 'UTC', 'quality' => 1],
            ],
        ]);

        self::assertSame('1.0.0', $input->sdkVersion);
        self::assertSame('n1', $input->nonce);
        self::assertFalse($input->highEntropyConsent);
        self::assertSame(1, $input->schemaVersion);
    }
}
