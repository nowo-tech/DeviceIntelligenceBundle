<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Event;

use Nowo\DeviceIntelligence\Analysis;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Alias wrapping the same data as RiskAssessmentCompletedEvent.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DeviceRiskCalculatedEvent extends Event
{
    /**
     * Creates the device risk calculated event.
     */
    public function __construct(public Analysis $analysis)
    {
    }

    /**
     * Creates the event from a risk assessment.
     */
    public static function fromAssessment(RiskAssessmentCompletedEvent $event): self
    {
        return new self($event->analysis);
    }
}
