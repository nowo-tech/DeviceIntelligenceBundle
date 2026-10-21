<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Request;

use Nowo\DeviceIntelligence\Analysis;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Matching\DeviceMatch;
use Nowo\DeviceIntelligence\Risk\RiskAssessment;

/**
 * Request-scoped wrapper around a core Analysis.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DeviceContext
{
    /**
     * Creates the device context.
     */
    public function __construct(
        private Analysis $analysis,
        private bool $trusted = false,
    ) {
    }

    /**
     * Analysis of the current request.
     */
    public function analysis(): Analysis
    {
        return $this->analysis;
    }

    /**
     * Risk assessment of the current request.
     */
    public function risk(): RiskAssessment
    {
        return $this->analysis->risk();
    }

    /**
     * Device of the current request.
     */
    public function device(): Device
    {
        return $this->analysis->device();
    }

    /**
     * Device match of the current request.
     */
    public function match(): DeviceMatch
    {
        return $this->analysis->match();
    }

    /**
     * Whether it is new.
     */
    public function isNew(): bool
    {
        return $this->analysis->match()->isNewDevice();
    }

    /**
     * Whether it is trusted.
     */
    public function isTrusted(): bool
    {
        return $this->trusted;
    }

    /**
     * Copy of the context with the given trust state.
     */
    public function withTrusted(bool $trusted): self
    {
        return new self($this->analysis, $trusted);
    }
}
