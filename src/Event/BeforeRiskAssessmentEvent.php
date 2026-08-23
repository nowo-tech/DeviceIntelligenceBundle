<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Event;

use Nowo\DeviceIntelligence\AnalysisInput;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched immediately before core analyze(). Listeners must not mutate the matcher.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class BeforeRiskAssessmentEvent extends Event
{
    public function __construct(public AnalysisInput $input)
    {
    }
}
