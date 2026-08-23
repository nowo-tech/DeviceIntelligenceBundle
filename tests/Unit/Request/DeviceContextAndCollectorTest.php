<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Request;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\AnalysisInput;
use Nowo\DeviceIntelligence\DeviceIntelligence;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceUserRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryObservationRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryTrustedDeviceRepository;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligenceBundle\Profiler\DeviceIntelligenceDataCollector;
use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DeviceContextAndCollectorTest extends TestCase
{
    public function testContextAndProfiler(): void
    {
        $engine = DeviceIntelligence::create(
            new InMemoryDeviceRepository(),
            new InMemoryObservationRepository(),
            new InMemoryDeviceUserRepository(),
            new InMemoryTrustedDeviceRepository(),
        );
        $analysis = $engine->analyze(new AnalysisInput(new DateTimeImmutable(), SignalBag::empty(), '1.1.1.1'));
        $context  = new DeviceContext($analysis);
        $trusted  = $context->withTrusted(true);

        self::assertSame($analysis, $context->analysis());
        self::assertSame($analysis->risk(), $context->risk());
        self::assertSame($analysis->device(), $context->device());
        self::assertSame($analysis->match(), $context->match());
        self::assertSame($analysis->match()->isNewDevice(), $context->isNew());
        self::assertFalse($context->isTrusted());
        self::assertTrue($trusted->isTrusted());

        $collector = new DeviceIntelligenceDataCollector();
        $request   = Request::create('/');
        $request->attributes->set('_device', $trusted);
        $collector->collect($request, new Response());

        self::assertTrue($collector->hasContext());
        self::assertSame($analysis->device()->id->value, $collector->getDeviceId());
        self::assertSame($analysis->match()->isNewDevice(), $collector->isNew());
        self::assertTrue($collector->isTrusted());
        self::assertSame($analysis->riskScore(), $collector->getRisk());
        self::assertSame($analysis->riskLevel(), $collector->getRiskLevel());
        self::assertSame($analysis->observation()->id->value, $collector->getObservationId());
        self::assertSame('nowo_device_intelligence', $collector->getName());
        self::assertIsArray($collector->getReasons());
        self::assertIsArray($collector->getSignals());
        self::assertIsArray($collector->getTimings());
        $collector->reset();
        self::assertFalse($collector->hasContext());
        self::assertSame('', $collector->getDeviceId());
        self::assertFalse($collector->isNew());
        self::assertFalse($collector->isTrusted());
        self::assertSame(0.0, $collector->getConfidence());
        self::assertSame(0.0, $collector->getSimilarity());
        self::assertSame(0.0, $collector->getStability());
        self::assertSame(0, $collector->getRisk());
        self::assertSame('', $collector->getRiskLevel());
        self::assertSame([], $collector->getReasons());
        self::assertSame([], $collector->getSignals());
        self::assertSame([], $collector->getTimings());
        self::assertFalse($collector->isDegraded());
        self::assertSame('', $collector->getObservationId());
    }
}
