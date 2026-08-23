<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Tests\Unit;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\AnalysisInput;
use Nowo\DeviceIntelligence\DeviceIntelligence;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceUserRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryObservationRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryTrustedDeviceRepository;
use Nowo\DeviceIntelligence\Signal\Normalizer\BrowserVersionNormalizer;
use Nowo\DeviceIntelligence\Signal\Quality;
use Nowo\DeviceIntelligence\Signal\Signal;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligence\Signal\SignalFactory;
use Nowo\DeviceIntelligence\Signal\SignalName;
use Nowo\DeviceIntelligence\Signal\SignalSource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DeviceIntelligenceAnalyzeTest extends TestCase
{
    #[Test]
    public function secondVisitMatchesSameDevice(): void
    {
        $engine  = $this->engine();
        $now     = new DateTimeImmutable('2026-08-22T10:00:00Z');
        $signals = SignalFactory::bagFromClient([
            'platform'             => ['value' => 'MacIntel', 'quality' => 1],
            'canvas'               => ['value' => 'aabbccddeeff0011', 'quality' => 0.95],
            'webgl'                => ['value' => ['vendor' => 'Apple', 'renderer' => 'Apple GPU'], 'quality' => 0.9],
            'screen'               => ['value' => ['width' => 1440, 'height' => 900], 'quality' => 1],
            'timezone'             => ['value' => 'Europe/Madrid', 'quality' => 1],
            'client_hints'         => ['value' => ['brands' => [['brand' => 'Google Chrome', 'version' => '143']], 'platform' => 'macOS'], 'quality' => 0.9],
            'hardware_concurrency' => ['value' => 8, 'quality' => 1],
            'browser_capabilities' => ['value' => ['webp' => true], 'quality' => 1],
            'audio'                => ['value' => '1122334455667788', 'quality' => 0.9],
        ], $now);

        $first  = $engine->analyze(new AnalysisInput($now, $signals, '1.2.3.4', 'Mozilla/5.0 Chrome/143.0.0.0'));
        $second = $engine->analyze(new AnalysisInput($now->modify('+1 hour'), $signals, '5.6.7.8', 'Mozilla/5.0 Chrome/143.0.0.0'));

        self::assertFalse($first->match()->isNewDevice() && $second->match()->isNewDevice() && $first->device()->id->equals($second->device()->id) === false);
        self::assertTrue($second->device()->id->equals($first->device()->id));
        self::assertFalse($second->match()->isNewDevice());
        self::assertSame(2, $second->device()->observationCount);
    }

    #[Test]
    public function levelZeroStillReturnsAnalysis(): void
    {
        $engine   = $this->engine();
        $now      = new DateTimeImmutable('2026-08-22T10:00:00Z');
        $analysis = $engine->analyze(new AnalysisInput($now, SignalBag::empty(), '10.0.0.1', 'Mozilla/5.0 Firefox/128.0'));

        self::assertTrue($analysis->degraded());
        self::assertNotSame('', $analysis->device()->id->value);
        self::assertContains($analysis->risk()->level()->value, ['low', 'medium', 'high', 'critical']);
    }

    #[Test]
    public function browserNormalizerKeepsMajorOnly(): void
    {
        $n      = new BrowserVersionNormalizer();
        $now    = new DateTimeImmutable();
        $signal = new Signal(
            SignalName::UserAgent,
            'Mozilla/5.0 Chrome/143.0.7312.58 Safari/537.36',
            'Mozilla/5.0 Chrome/143.0.7312.58 Safari/537.36',
            new Quality(1),
            0.5,
            SignalName::UserAgent->entropyCategory(),
            $now,
            SignalSource::Server,
        );
        $out = $n->normalize($signal);
        self::assertSame('Chrome 143', $out->normalizedValue);
    }

    private function engine(): DeviceIntelligence
    {
        return DeviceIntelligence::create(
            new InMemoryDeviceRepository(),
            new InMemoryObservationRepository(),
            new InMemoryDeviceUserRepository(),
            new InMemoryTrustedDeviceRepository(),
        );
    }
}
