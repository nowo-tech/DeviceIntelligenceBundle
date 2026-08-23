<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Controller;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\DeviceIntelligence;
use Nowo\DeviceIntelligence\Infrastructure\FrozenClock;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceUserRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryObservationRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryTrustedDeviceRepository;
use Nowo\DeviceIntelligence\Privacy\PrivacyContext;
use Nowo\DeviceIntelligence\Privacy\PrivacyMode;
use Nowo\DeviceIntelligenceBundle\Config\DeviceIntelligenceConfig;
use Nowo\DeviceIntelligenceBundle\Controller\CollectController;
use Nowo\DeviceIntelligenceBundle\Event\AnalyzeService;
use Nowo\DeviceIntelligenceBundle\Http\AnalysisInputFactory;
use Nowo\DeviceIntelligenceBundle\Http\CollectRequestValidator;
use Nowo\DeviceIntelligenceBundle\Http\ObservationTokenIssuer;
use Nowo\DeviceIntelligenceBundle\Http\OriginValidator;
use Nowo\DeviceIntelligenceBundle\RateLimiter\DeviceRateLimiterInterface;
use Nowo\DeviceIntelligenceBundle\Tests\Support\ProcessedConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

use const JSON_THROW_ON_ERROR;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class CollectControllerTest extends TestCase
{
    public function testDisabledReturns404(): void
    {
        $controller = $this->controller(ProcessedConfig::object(['enabled' => false]));
        $response   = $controller(Request::create('/_device/collect', 'POST'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testRateLimitReturns403(): void
    {
        $limiter = $this->createMock(DeviceRateLimiterInterface::class);
        $limiter->method('consume')->willReturn(false);
        $controller = $this->controller(ProcessedConfig::object(['endpoint' => ['csrf' => 'none']]), $limiter);
        $response   = $controller(Request::create('/_device/collect', 'POST'));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testValidCollectReturnsOk(): void
    {
        $now        = new DateTimeImmutable();
        $controller = $this->controller(
            ProcessedConfig::object([
                'endpoint' => [
                    'csrf'     => 'none',
                    'response' => [
                        'token'      => true,
                        'device_id'  => true,
                        'confidence' => true,
                        'risk'       => true,
                    ],
                ],
            ]),
            clock: $now,
        );
        $payload = json_encode([
            'v'          => 1,
            'sdkVersion' => '1.0.0',
            'timestamp'  => $now->getTimestamp(),
            'nonce'      => 'nonce-collect-1',
            'consent'    => ['highEntropy' => true],
            'signals'    => [
                'platform' => ['value' => 'MacIntel', 'quality' => 1],
                'timezone' => ['value' => 'Europe/Madrid', 'quality' => 1],
            ],
        ], JSON_THROW_ON_ERROR);
        $request = Request::create('/_device/collect', 'POST', server: [
            'REMOTE_ADDR'     => '203.0.113.10',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/143.0.0.0',
        ], content: $payload);
        $response = $controller($request);
        $data     = json_decode((string) $response->getContent(), true);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($data['ok']);
        self::assertArrayHasKey('token', $data);
        self::assertArrayHasKey('deviceId', $data);
        self::assertArrayHasKey('risk', $data);
        self::assertNotNull($request->attributes->get('_device'));
    }

    public function testInvalidJsonIsRejected(): void
    {
        $controller = $this->controller(ProcessedConfig::object(['endpoint' => ['csrf' => 'none']]));
        $request    = Request::create('/_device/collect', 'POST', content: '{');
        $response   = $controller($request);

        self::assertSame(400, $response->getStatusCode());
    }

    private function controller(
        DeviceIntelligenceConfig $config,
        ?DeviceRateLimiterInterface $limiter = null,
        ?DateTimeImmutable $clock = null,
    ): CollectController {
        $now       = $clock ?? new DateTimeImmutable();
        $frozen    = new FrozenClock($now);
        $cache     = new Psr16Cache(new ArrayAdapter());
        $origins   = new OriginValidator($config);
        $validator = new CollectRequestValidator($config, $origins, $cache);
        $privacy   = new PrivacyContext(PrivacyMode::Balanced);
        $inputs    = new AnalysisInputFactory($config, $frozen, $privacy, 'kernel-secret');
        $engine    = DeviceIntelligence::create(
            new InMemoryDeviceRepository(),
            new InMemoryObservationRepository(),
            new InMemoryDeviceUserRepository(),
            new InMemoryTrustedDeviceRepository(),
        );
        $analyze = new AnalyzeService($engine, new EventDispatcher());
        $tokens  = new ObservationTokenIssuer($config, $frozen, 'kernel-secret');
        $limiter ??= $this->allowAllLimiter();

        return new CollectController($validator, $inputs, $analyze, $tokens, $config, $limiter, new NullLogger());
    }

    private function allowAllLimiter(): DeviceRateLimiterInterface
    {
        $limiter = $this->createMock(DeviceRateLimiterInterface::class);
        $limiter->method('consume')->willReturn(true);

        return $limiter;
    }
}
