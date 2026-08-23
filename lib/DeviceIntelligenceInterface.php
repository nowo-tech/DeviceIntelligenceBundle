<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence;

interface DeviceIntelligenceInterface
{
    public function analyze(AnalysisInput $input): Analysis;
}
