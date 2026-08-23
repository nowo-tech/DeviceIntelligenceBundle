<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Event;

use Nowo\DeviceIntelligence\AnalysisInput;
use Nowo\DeviceIntelligence\DeviceIntelligence;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceUserRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryObservationRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryTrustedDeviceRepository;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligence\Signal\SignalFactory;
use Nowo\DeviceIntelligenceBundle\Event\AnalyzeService;
use Nowo\DeviceIntelligenceBundle\Event\BeforeRiskAssessmentEvent;
use Nowo\DeviceIntelligenceBundle\Event\DeviceCreatedEvent;
use Nowo\DeviceIntelligenceBundle\Event\DeviceMatchedEvent;
use Nowo\DeviceIntelligenceBundle\Event\DeviceObservedEvent;
use Nowo\DeviceIntelligenceBundle\Event\DeviceRiskCalculatedEvent;
use Nowo\DeviceIntelligenceBundle\Event\NewDeviceDetectedEvent;
use Nowo\DeviceIntelligenceBundle\Event\RiskAssessmentCompletedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class AnalyzeServiceTest extends TestCase
{
    public function testDispatchesCreateEventsOnFirstVisit(): void
    {
        $dispatcher = new EventDispatcher();
        $seen = [];
        foreach ([
            BeforeRiskAssessmentEvent::class,
            DeviceObservedEvent::class,
            DeviceCreatedEvent::class,
            NewDeviceDetectedEvent::class,
            RiskAssessmentCompletedEvent::class,
            DeviceRiskCalculatedEvent::class,
        ] as $class) {
            $dispatcher->addListener($class, static function () use (&$seen, $class): void {
                $seen[] = $class;
            });
        }

        $service = new AnalyzeService($this->engine(), $dispatcher);
        $analysis = $service->analyze(new AnalysisInput(new \DateTimeImmutable(), SignalBag::empty(), '10.0.0.1'));

        self::assertTrue($analysis->match()->isNewDevice());
        self::assertContains(DeviceCreatedEvent::class, $seen);
        self::assertContains(DeviceRiskCalculatedEvent::class, $seen);
        self::assertNotContains(DeviceMatchedEvent::class, $seen);
    }

    public function testDispatchesMatchOnSecondVisit(): void
    {
        $engine = $this->engine();
        $service = new AnalyzeService($engine, new EventDispatcher());
        $now = new \DateTimeImmutable('2026-08-23T10:00:00Z');
        $signals = SignalFactory::bagFromClient([
            'platform' => ['value' => 'MacIntel', 'quality' => 1],
            'canvas' => ['value' => 'aabbccddeeff0011', 'quality' => 0.95],
            'webgl' => ['value' => ['vendor' => 'Apple', 'renderer' => 'Apple GPU'], 'quality' => 0.9],
            'screen' => ['value' => ['width' => 1440, 'height' => 900], 'quality' => 1],
            'timezone' => ['value' => 'Europe/Madrid', 'quality' => 1],
            'client_hints' => ['value' => ['brands' => [['brand' => 'Google Chrome', 'version' => '143']], 'platform' => 'macOS'], 'quality' => 0.9],
            'hardware_concurrency' => ['value' => 8, 'quality' => 1],
            'browser_capabilities' => ['value' => ['webp' => true], 'quality' => 1],
            'audio' => ['value' => '1122334455667788', 'quality' => 0.9],
        ], $now);
        $service->analyze(new AnalysisInput($now, $signals, '10.0.0.1', 'Mozilla/5.0 Chrome/143.0.0.0'));

        $dispatcher = new EventDispatcher();
        $matched = false;
        $dispatcher->addListener(DeviceMatchedEvent::class, static function () use (&$matched): void {
            $matched = true;
        });
        $again = new AnalyzeService($engine, $dispatcher);
        $second = $again->analyze(new AnalysisInput($now->modify('+1 hour'), $signals, '10.0.0.1', 'Mozilla/5.0 Chrome/143.0.0.0'));

        self::assertFalse($second->match()->isNewDevice());
        self::assertTrue($matched);
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
