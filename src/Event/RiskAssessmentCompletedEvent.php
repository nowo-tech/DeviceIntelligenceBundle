<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Event;

use Nowo\DeviceIntelligence\Analysis;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after core risk assessment is stored on the observation.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class RiskAssessmentCompletedEvent extends Event
{
    /**
     * Creates the risk assessment completed event.
     */
    public function __construct(public Analysis $analysis)
    {
    }
}
