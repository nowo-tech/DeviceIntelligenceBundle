<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Profiler;

use Nowo\DeviceIntelligence\Analysis;
use Nowo\DeviceIntelligenceBundle\Config\DeviceIntelligenceConfig;
use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Throwable;

/**
 * Web Profiler panel. Signal values are truncated summaries only.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DeviceIntelligenceDataCollector extends DataCollector
{
    /**
     * Creates the device intelligence data collector.
     */
    public function __construct(private ?DeviceIntelligenceConfig $config = null)
    {
    }

    /**
     * Collects the device context of the request for the profiler.
     */
    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        unset($response, $exception);
        $cookieName = $this->cookieName();
        $context = $request->attributes->get('_device');
        if ($context instanceof DeviceContext) {
            $this->collectAnalysis($context->analysis(), $context->isTrusted());
        } else {
            // @igor-ignore - Symfony profiler data collector; debug-only per-request aggregation
            $this->data['has_context'] ??= false;
        }

        // @igor-ignore - Symfony profiler data collector; debug-only per-request aggregation

        // @igor-ignore - Symfony profiler data collector; debug-only per-request aggregation
        $this->data['cookie_present'] = $request->cookies->has($cookieName);
        // @igor-ignore - Symfony profiler data collector; debug-only per-request aggregation
        $this->data['cookie_name'] = $cookieName;
        // @igor-ignore - Symfony profiler data collector; debug-only per-request aggregation
        $this->data['request_method'] = $request->getMethod();
        // @igor-ignore - Symfony profiler data collector; debug-only per-request aggregation
        $this->data['request_path'] = $request->getPathInfo();
    }

    /**
     * Stores an analysis (and its trust state) for the profiler panel.
     */
    public function collectAnalysis(Analysis $analysis, bool $trusted = false, string $source = 'request'): void
    {
        $summaries = [];
        foreach ($analysis->signals() as $name => $signal) {
            // @igor-ignore - Symfony profiler data collector; debug-only per-request aggregation
            $summaries[$name] = $signal->summary(48);
            // @igor-ignore - Symfony profiler data collector; debug-only per-request aggregation
        }

        // @igor-ignore - Symfony profiler data collector; debug-only per-request aggregation

        // @igor-ignore - Symfony profiler data collector; debug-only per-request aggregation
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
            'source' => $source,
            'cookie_present' => (bool) ($this->data['cookie_present'] ?? false),
            'cookie_name' => (string) ($this->data['cookie_name'] ?? $this->cookieName()),
            'request_method' => (string) ($this->data['request_method'] ?? ''),
            'request_path' => (string) ($this->data['request_path'] ?? ''),
        ];
    }

    /**
     * Returns the name.
     */
    public function getName(): string
    {
        return 'nowo_device_intelligence';
    }

    /**
     * Clears the collected data.
     */
    public function reset(): void
    {
        $this->data = [];
    }

    /**
     * Whether a device context was collected.
     */
    public function hasContext(): bool
    {
        return (bool) ($this->data['has_context'] ?? false);
    }

    /**
     * Whether the data comes from an AJAX request.
     */
    public function isFromAjax(): bool
    {
        return 'ajax' === ($this->data['source'] ?? '');
    }

    /**
     * Whether the device cookie was present on the request.
     */
    public function isCookiePresent(): bool
    {
        return (bool) ($this->data['cookie_present'] ?? false);
    }

    /**
     * Returns the cookie name.
     */
    public function getCookieName(): string
    {
        return (string) ($this->data['cookie_name'] ?? $this->cookieName());
    }

    /**
     * Returns the request method.
     */
    public function getRequestMethod(): string
    {
        return (string) ($this->data['request_method'] ?? '');
    }

    /**
     * Returns the request path.
     */
    public function getRequestPath(): string
    {
        return (string) ($this->data['request_path'] ?? '');
    }

    /**
     * Returns the device id.
     */
    public function getDeviceId(): string
    {
        return (string) ($this->data['device_id'] ?? '');
    }

    /**
     * Whether it is new.
     */
    public function isNew(): bool
    {
        return (bool) ($this->data['new'] ?? false);
    }

    /**
     * Whether it is trusted.
     */
    public function isTrusted(): bool
    {
        return (bool) ($this->data['trusted'] ?? false);
    }

    /**
     * Returns the confidence.
     */
    public function getConfidence(): float
    {
        return (float) ($this->data['confidence'] ?? 0.0);
    }

    /**
     * Returns the similarity.
     */
    public function getSimilarity(): float
    {
        return (float) ($this->data['similarity'] ?? 0.0);
    }

    /**
     * Returns the stability.
     */
    public function getStability(): float
    {
        return (float) ($this->data['stability'] ?? 0.0);
    }

    /**
     * Returns the risk.
     */
    public function getRisk(): int
    {
        return (int) ($this->data['risk'] ?? 0);
    }

    /**
     * Returns the risk level.
     */
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

    /**
     * Whether it is degraded.
     */
    public function isDegraded(): bool
    {
        return (bool) ($this->data['degraded'] ?? false);
    }

    /**
     * Returns the observation id.
     */
    public function getObservationId(): string
    {
        return (string) ($this->data['observation_id'] ?? '');
    }

    private function cookieName(): string
    {
        $name = $this->config?->tokenCookie()['name'] ?? 'di_obs';

        return \is_string($name) && '' !== $name ? $name : 'di_obs';
    }
}
