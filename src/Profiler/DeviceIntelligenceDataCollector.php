<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Profiler;

use Nowo\DeviceIntelligence\Analysis;
use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Web Profiler panel. Signal values are truncated summaries only.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DeviceIntelligenceDataCollector extends DataCollector
{
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        unset($response, $exception);
        $context = $request->attributes->get('_device');
        if ($context instanceof DeviceContext) {
            $this->collectAnalysis($context->analysis(), $context->isTrusted());
        }
        $this->data['has_context'] ??= false;
    }

    public function collectAnalysis(Analysis $analysis, bool $trusted = false): void
    {
        $summaries = [];
        foreach ($analysis->signals() as $name => $signal) {
            $summaries[$name] = $signal->summary(48);
        }

        $this->data = [
            'has_context' => true,
            'device_id' => $analysis->device()->id->value,
            'new' => $analysis->match()->isNewDevice(),
            'trusted' => $trusted,
            'confidence' => $analysis->match()->confidence(),
            'similarity' => $analysis->match()->similarity(),
            'stability' => $analysis->device()->stability(),
            'risk' => $analysis->riskScore(),
            'risk_level' => $analysis->riskLevel(),
            'reasons' => $analysis->riskReasons(),
            'signals' => $summaries,
            'timings' => $analysis->timings,
            'degraded' => $analysis->degraded(),
            'observation_id' => $analysis->observation()->id->value,
        ];
    }

    public function getName(): string
    {
        return 'nowo_device_intelligence';
    }

    public function reset(): void
    {
        $this->data = [];
    }

    public function hasContext(): bool
    {
        return (bool) ($this->data['has_context'] ?? false);
    }

    public function getDeviceId(): string
    {
        return (string) ($this->data['device_id'] ?? '');
    }

    public function isNew(): bool
    {
        return (bool) ($this->data['new'] ?? false);
    }

    public function isTrusted(): bool
    {
        return (bool) ($this->data['trusted'] ?? false);
    }

    public function getConfidence(): float
    {
        return (float) ($this->data['confidence'] ?? 0.0);
    }

    public function getSimilarity(): float
    {
        return (float) ($this->data['similarity'] ?? 0.0);
    }

    public function getStability(): float
    {
        return (float) ($this->data['stability'] ?? 0.0);
    }

    public function getRisk(): int
    {
        return (int) ($this->data['risk'] ?? 0);
    }

    public function getRiskLevel(): string
    {
        return (string) ($this->data['risk_level'] ?? '');
    }

    /**
     * @return list<string>
     */
    public function getReasons(): array
    {
        $reasons = $this->data['reasons'] ?? [];

        return \is_array($reasons) ? array_values($reasons) : [];
    }

    /**
     * @return array<string, string>
     */
    public function getSignals(): array
    {
        $signals = $this->data['signals'] ?? [];

        return \is_array($signals) ? $signals : [];
    }

    /**
     * @return array<string, float>
     */
    public function getTimings(): array
    {
        $timings = $this->data['timings'] ?? [];

        return \is_array($timings) ? $timings : [];
    }

    public function isDegraded(): bool
    {
        return (bool) ($this->data['degraded'] ?? false);
    }

    public function getObservationId(): string
    {
        return (string) ($this->data['observation_id'] ?? '');
    }
}
