<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Support;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Device\DeviceStatus;
use Nowo\DeviceIntelligence\Device\Stability;
use Nowo\DeviceIntelligence\Matching\CandidateIndexKey;
use Nowo\DeviceIntelligence\Matching\Confidence;
use Nowo\DeviceIntelligence\Matching\DeviceMatch;
use Nowo\DeviceIntelligence\Matching\Similarity;
use Nowo\DeviceIntelligence\Observation\DeviceObservation;
use Nowo\DeviceIntelligence\Observation\ObservationId;
use Nowo\DeviceIntelligence\Privacy\IpHash;
use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Signal\Quality;
use Nowo\DeviceIntelligence\Signal\Signal;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligence\Signal\SignalName;
use Nowo\DeviceIntelligence\Signal\SignalSource;
use Nowo\DeviceIntelligence\User\DeviceUserRelation;
use Nowo\DeviceIntelligence\User\UserIdentifier;

/**
 * Shared builders for unit tests.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class Scenario
{
    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-23T12:00:00+00:00');
    }

    public static function signal(
        SignalName $name,
        mixed $value,
        mixed $normalized = null,
        float $quality = 1.0,
        float $stability = 0.9,
        ?DateTimeImmutable $at = null,
    ): Signal {
        return new Signal(
            $name,
            $value,
            $normalized ?? $value,
            new Quality($quality),
            $stability,
            $name->entropyCategory(),
            $at ?? self::now(),
            SignalSource::Client,
        );
    }

    public static function bag(Signal ...$signals): SignalBag
    {
        $bag = SignalBag::empty();
        foreach ($signals as $signal) {
            $bag = $bag->with($signal);
        }

        return $bag;
    }

    public static function device(
        ?DateTimeImmutable $now = null,
        ?SignalBag $signals = null,
        DeviceStatus $status = DeviceStatus::Active,
        string $os = 'macos',
        string $browser = 'chrome',
        string $gpu = 'apple',
        string $tz = 'Europe/Madrid',
    ): Device {
        $now ??= self::now();

        return new Device(
            DeviceId::generate($now),
            $now,
            $now,
            3,
            new Confidence(0.82),
            new Stability(0.71),
            $status,
            new CandidateIndexKey($os, $browser, $gpu, 'desktop', $tz, 'abcd'),
            'Chrome on macOS',
            [],
            $signals ?? SignalBag::empty(),
        );
    }

    public static function observation(
        Device $device,
        ?SignalBag $signals = null,
        ?DateTimeImmutable $now = null,
        ?string $country = 'ES',
        ?string $session = 'sess-1',
        ?IpHash $ipHash = null,
        ?UserIdentifier $user = null,
    ): DeviceObservation {
        $now ??= self::now();

        return new DeviceObservation(
            ObservationId::generate($now),
            $device->id,
            $now,
            1,
            '1.0.0',
            $ipHash,
            $country,
            'Chrome',
            'Mozilla/5.0',
            $session,
            $user,
            $signals ?? $device->lastSignals,
            0,
            false,
            2,
        );
    }

    /**
     * @param list<DeviceUserRelation> $relations
     * @param array<string, int> $velocity
     */
    public static function context(
        Device $device,
        DeviceObservation $observation,
        bool $new = false,
        array $relations = [],
        array $velocity = [],
        bool $trusted = false,
        ?string $previousCountry = null,
        ?string $previousIp = null,
        ?string $previousSession = null,
        mixed $geo = null,
    ): RiskContext {
        $match = new DeviceMatch(
            $new ? null : $device,
            new Confidence(0.8),
            new Similarity(0.8),
            [],
            [],
            $new,
        );

        return new RiskContext(
            $observation,
            $device,
            $match,
            $relations,
            $velocity,
            $trusted,
            $geo,
            null,
            $previousCountry,
            $previousIp,
            $previousSession,
        );
    }
}
