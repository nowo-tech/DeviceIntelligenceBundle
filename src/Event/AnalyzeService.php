<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Event;

use Nowo\DeviceIntelligence\Analysis;
use Nowo\DeviceIntelligence\AnalysisInput;
use Nowo\DeviceIntelligence\DeviceIntelligence;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Wraps core analyze() and dispatches Symfony events. Does not mutate the matcher.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class AnalyzeService
{
    public function __construct(
        private DeviceIntelligence $engine,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function analyze(AnalysisInput $input): Analysis
    {
        $this->dispatcher->dispatch(new BeforeRiskAssessmentEvent($input));

        $analysis = $this->engine->analyze($input);

        $this->dispatcher->dispatch(new DeviceObservedEvent($analysis));
        if ($analysis->match()->isNewDevice()) {
            $this->dispatcher->dispatch(new DeviceCreatedEvent($analysis));
            $this->dispatcher->dispatch(new NewDeviceDetectedEvent($analysis));
        } else {
            $this->dispatcher->dispatch(new DeviceMatchedEvent($analysis));
        }

        $completed = new RiskAssessmentCompletedEvent($analysis);
        $this->dispatcher->dispatch($completed);
        $this->dispatcher->dispatch(DeviceRiskCalculatedEvent::fromAssessment($completed));

        if ($analysis->risk()->isHigh()) {
            $this->dispatcher->dispatch(new SuspiciousDeviceEvent($analysis));
        }

        return $analysis;
    }
}
