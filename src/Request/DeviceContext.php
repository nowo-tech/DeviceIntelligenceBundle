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
    public function __construct(
        private Analysis $analysis,
        private bool $trusted = false,
    ) {
    }

    public function analysis(): Analysis
    {
        return $this->analysis;
    }

    public function risk(): RiskAssessment
    {
        return $this->analysis->risk();
    }

    public function device(): Device
    {
        return $this->analysis->device();
    }

    public function match(): DeviceMatch
    {
        return $this->analysis->match();
    }

    public function isNew(): bool
    {
        return $this->analysis->match()->isNewDevice();
    }

    public function isTrusted(): bool
    {
        return $this->trusted;
    }

    public function withTrusted(bool $trusted): self
    {
        return new self($this->analysis, $trusted);
    }
}
