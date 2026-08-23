<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Device;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Matching\CandidateIndexKey;
use Nowo\DeviceIntelligence\Matching\Confidence;
use Nowo\DeviceIntelligence\Observation\DeviceObservation;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligence\Signal\SignalName;

final readonly class Device
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public DeviceId $id,
        public DateTimeImmutable $firstSeenAt,
        public DateTimeImmutable $lastSeenAt,
        public int $observationCount,
        public Confidence $confidence,
        public Stability $stability,
        public DeviceStatus $status,
        public CandidateIndexKey $indexKey,
        public string $label = '',
        public array $metadata = [],
        public SignalBag $lastSignals = new SignalBag([]),
    ) {
    }

    public function stability(): float
    {
        return $this->stability->value;
    }

    public function withObservation(
        DateTimeImmutable $seenAt,
        Confidence $confidence,
        Stability $stability,
        CandidateIndexKey $indexKey,
        SignalBag $signals,
        string $label,
    ): self {
        return new self(
            $this->id,
            $this->firstSeenAt,
            $seenAt,
            $this->observationCount + 1,
            $confidence,
            $stability,
            $this->status,
            $this->mergeIndexKey($indexKey),
            $label !== '' ? $label : $this->label,
            $this->metadata,
            $signals,
        );
    }

    public function compare(DeviceObservation $observation): MutationReport
    {
        $changed     = [];
        $stable      = [];
        $mass        = 0.0;
        $changedMass = 0.0;
        foreach ($observation->signals as $signal) {
            $previous = $this->lastSignals->get($signal->name);
            if ($previous === null) {
                continue;
            }
            $same   = json_encode($previous->normalizedValue) === json_encode($signal->normalizedValue);
            $weight = $signal->name->expectedStability();
            $mass += $weight;
            if ($same) {
                $stable[] = $signal->name;
            } else {
                $changed[] = $signal->name;
                $changedMass += $weight;
            }
        }

        $score = $mass > 0.0 ? $changedMass / $mass : 0.0;

        return new MutationReport($changed, $stable, $score);
    }

    private function mergeIndexKey(CandidateIndexKey $incoming): CandidateIndexKey
    {
        return new CandidateIndexKey(
            $incoming->osFamily !== 'other' ? $incoming->osFamily : $this->indexKey->osFamily,
            $incoming->browserFamily !== 'other' ? $incoming->browserFamily : $this->indexKey->browserFamily,
            $incoming->gpuFamily !== 'other' ? $incoming->gpuFamily : $this->indexKey->gpuFamily,
            $incoming->screenClass !== 'other' ? $incoming->screenClass : $this->indexKey->screenClass,
            $incoming->timezone !== '' && $incoming->timezone !== 'UTC' ? $incoming->timezone : $this->indexKey->timezone,
            $incoming->blockingKey !== '' ? $incoming->blockingKey : $this->indexKey->blockingKey,
        );
    }

    public static function fromNew(
        DeviceId $id,
        DateTimeImmutable $now,
        CandidateIndexKey $key,
        SignalBag $signals,
        string $label,
    ): self {
        return new self(
            $id,
            $now,
            $now,
            1,
            new Confidence(0.5),
            new Stability(0.5),
            DeviceStatus::Active,
            $key,
            $label,
            [],
            $signals,
        );
    }

    /**
     * @return list<SignalName>
     */
    public function historicallyStableSignals(): array
    {
        $out = [];
        foreach ($this->lastSignals as $signal) {
            if ($signal->stability >= 0.8) {
                $out[] = $signal->name;
            }
        }

        return $out;
    }
}
