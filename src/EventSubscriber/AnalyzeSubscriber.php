<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\EventSubscriber;

use Nowo\DeviceIntelligenceBundle\Event\DeviceObservedEvent;
use Nowo\DeviceIntelligenceBundle\Profiler\DeviceIntelligenceDataCollector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Forwards post-analyze results to the profiler. Does not mutate matching.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class AnalyzeSubscriber implements EventSubscriberInterface
{
    public function __construct(private ?DeviceIntelligenceDataCollector $collector = null)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DeviceObservedEvent::class => 'onObserved',
        ];
    }

    public function onObserved(DeviceObservedEvent $event): void
    {
        $this->collector?->collectAnalysis($event->analysis);
    }
}
