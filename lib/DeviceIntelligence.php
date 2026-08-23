<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence;

use Nowo\DeviceIntelligence\Device\DefaultDeviceLabeler;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Device\DeviceLabelerInterface;
use Nowo\DeviceIntelligence\Device\Stability;
use Nowo\DeviceIntelligence\Matching\Candidate\DeviceCandidateProviderInterface;
use Nowo\DeviceIntelligence\Matching\Candidate\RepositoryCandidateProvider;
use Nowo\DeviceIntelligence\Matching\Confidence;
use Nowo\DeviceIntelligence\Matching\DeviceMatch;
use Nowo\DeviceIntelligence\Matching\DeviceMatcherInterface;
use Nowo\DeviceIntelligence\Matching\IndexKeyFactory;
use Nowo\DeviceIntelligence\Matching\WeightedDeviceMatcher;
use Nowo\DeviceIntelligence\Observation\DeviceObservation;
use Nowo\DeviceIntelligence\Observation\ObservationId;
use Nowo\DeviceIntelligence\Port\DeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Port\DeviceUserRepositoryInterface;
use Nowo\DeviceIntelligence\Port\GeoIpProviderInterface;
use Nowo\DeviceIntelligence\Port\MetricsRecorderInterface;
use Nowo\DeviceIntelligence\Port\NullGeoIpProvider;
use Nowo\DeviceIntelligence\Port\NullMetricsRecorder;
use Nowo\DeviceIntelligence\Port\ObservationRepositoryInterface;
use Nowo\DeviceIntelligence\Port\TrustedDeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Privacy\IpHasher;
use Nowo\DeviceIntelligence\Privacy\PrivacyProcessor;
use Nowo\DeviceIntelligence\Privacy\PrivacyProcessorInterface;
use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Risk\RiskEngine;
use Nowo\DeviceIntelligence\Risk\RiskEngineInterface;
use Nowo\DeviceIntelligence\Signal\ClientHintPlatformBridge;
use Nowo\DeviceIntelligence\Signal\EnhancementLevel;
use Nowo\DeviceIntelligence\Signal\Normalizer\SignalNormalizerRegistry;
use Nowo\DeviceIntelligence\Signal\Server\DefaultServerSignalProvider;
use Nowo\DeviceIntelligence\Signal\Server\NetworkSignalProviderInterface;
use Nowo\DeviceIntelligence\Signal\Server\NullNetworkSignalProvider;
use Nowo\DeviceIntelligence\Signal\Server\ServerSignalProviderInterface;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligence\Signal\SignalName;
use Nowo\DeviceIntelligence\Velocity\InMemoryVelocityEngine;
use Nowo\DeviceIntelligence\Velocity\TimeWindow;
use Nowo\DeviceIntelligence\Velocity\VelocityEngineInterface;

use function count;
use function is_array;

final class DeviceIntelligence implements DeviceIntelligenceInterface
{
    public function __construct(
        private DeviceRepositoryInterface $devices,
        private ObservationRepositoryInterface $observations,
        private DeviceUserRepositoryInterface $deviceUsers,
        private TrustedDeviceRepositoryInterface $trusts,
        private DeviceMatcherInterface $matcher = new WeightedDeviceMatcher(),
        private ?DeviceCandidateProviderInterface $candidates = null,
        private SignalNormalizerRegistry $normalizers = new SignalNormalizerRegistry([]),
        private PrivacyProcessorInterface $privacy = new PrivacyProcessor(),
        private RiskEngineInterface $risk = new RiskEngine([]),
        private DeviceLabelerInterface $labeler = new DefaultDeviceLabeler(),
        private ServerSignalProviderInterface $serverSignals = new DefaultServerSignalProvider(),
        private NetworkSignalProviderInterface $networkSignals = new NullNetworkSignalProvider(),
        private GeoIpProviderInterface $geoIp = new NullGeoIpProvider(),
        private VelocityEngineInterface $velocity = new InMemoryVelocityEngine(),
        private MetricsRecorderInterface $metrics = new NullMetricsRecorder(),
        private IndexKeyFactory $indexKeys = new IndexKeyFactory(),
    ) {
    }

    /**
     * Preferred constructor with default collaborators.
     */
    public static function create(
        DeviceRepositoryInterface $devices,
        ObservationRepositoryInterface $observations,
        DeviceUserRepositoryInterface $deviceUsers,
        TrustedDeviceRepositoryInterface $trusts,
        ?DeviceMatcherInterface $matcher = null,
        ?RiskEngineInterface $risk = null,
        ?VelocityEngineInterface $velocity = null,
    ): self {
        return new self(
            $devices,
            $observations,
            $deviceUsers,
            $trusts,
            $matcher ?? new WeightedDeviceMatcher(),
            new RepositoryCandidateProvider($devices),
            SignalNormalizerRegistry::defaults(),
            new PrivacyProcessor(),
            $risk ?? RiskEngine::defaults(),
            new DefaultDeviceLabeler(),
            new DefaultServerSignalProvider(),
            new NullNetworkSignalProvider(),
            new NullGeoIpProvider(),
            $velocity ?? new InMemoryVelocityEngine(),
            new NullMetricsRecorder(),
            new IndexKeyFactory(),
        );
    }

    public function analyze(AnalysisInput $input): Analysis
    {
        $t0  = hrtime(true);
        $bag = $input->clientSignals;
        foreach ($this->serverSignals->collect($input) as $signal) {
            if (!$bag->has($signal->name)) {
                $bag = $bag->with($signal);
            }
        }
        foreach ($this->networkSignals->collect($input) as $signal) {
            $bag = $bag->with($signal);
        }
        $bag        = $this->privacy->process($bag, $input->privacy);
        $normalized = SignalBag::empty();
        foreach ($bag as $signal) {
            $normalized = $normalized->with($this->normalizers->normalize($signal));
        }
        $normalized = ClientHintPlatformBridge::platformFromHints($normalized, $input->now);
        $tNormalize = (hrtime(true) - $t0) / 1e6;

        $degraded = EnhancementLevel::of($normalized) === 0;
        $ipHash   = IpHasher::hash($input->clientIp, $input->ipSalt, $input->privacy->hashIp);
        $country  = null;
        $geo      = null;
        if ($input->clientIp !== null) {
            $geo     = $this->geoIp->locate($input->clientIp);
            $country = $geo?->country;
        }

        $placeholderId = DeviceId::generate($input->now);
        $draft         = new DeviceObservation(
            ObservationId::generate($input->now),
            $placeholderId,
            $input->now,
            $input->schemaVersion,
            $input->sdkVersion,
            $ipHash,
            $country,
            $this->uaFamily($normalized),
            $input->privacy->storeUserAgent ? $input->userAgent : null,
            $input->sessionId,
            $input->userIdentifier,
            $normalized,
            0,
            $degraded,
            EnhancementLevel::of($normalized),
        );

        $tCand0     = hrtime(true);
        $provider   = $this->candidates ?? new RepositoryCandidateProvider($this->devices);
        $candidates = $provider->candidates($draft);
        $tCand      = (hrtime(true) - $tCand0) / 1e6;

        $tMatch0 = hrtime(true);
        $match   = $this->matcher->match($draft, $candidates);
        $tMatch  = (hrtime(true) - $tMatch0) / 1e6;

        $key   = $this->indexKeys->fromSignals($normalized);
        $label = $this->labeler->label($normalized);
        if ($match->isNewDevice() || $match->device() === null) {
            $device = Device::fromNew(DeviceId::generate($input->now), $input->now, $key, $normalized, $label);
            $device = new Device(
                $device->id,
                $device->firstSeenAt,
                $device->lastSeenAt,
                $device->observationCount,
                new Confidence($match->confidence()),
                $device->stability,
                $device->status,
                $device->indexKey,
                $device->label,
                $device->metadata,
                $normalized,
            );
            $this->metrics->increment('device_intelligence.new_devices');
        } else {
            $existing  = $match->device();
            $stability = $this->emaStability($existing, $match);
            $device    = $existing->withObservation($input->now, new Confidence($match->confidence()), $stability, $key, $normalized, $label);
            $this->metrics->increment('device_intelligence.matches');
        }
        $this->devices->save($device);

        $observation = new DeviceObservation(
            $draft->id,
            $device->id,
            $draft->createdAt,
            $draft->schemaVersion,
            $draft->sdkVersion,
            $draft->ipHash,
            $draft->country,
            $draft->userAgentFamily,
            $draft->rawUserAgent,
            $draft->sessionIdentifier,
            $draft->userIdentifier,
            $draft->signals,
            0,
            $draft->degraded,
            $draft->enhancementLevel,
        );

        $latest   = $this->observations->latestForDevice($device, 1);
        $previous = $latest[0] ?? null;
        $trusted  = false;
        if ($input->userIdentifier !== null) {
            $trusted = $this->trusts->findActive($device->id, $input->userIdentifier, $input->now) !== null;
        }
        $this->velocity->increment('request', $device);
        $relations  = $this->deviceUsers->forDevice($device->id);
        $tRisk0     = hrtime(true);
        $assessment = $this->risk->assess(new RiskContext(
            $observation,
            $device,
            $match,
            $relations,
            [
                'request'       => $this->velocity->count('request', $device, TimeWindow::parse('1 hour')),
                'registration'  => $this->velocity->count('registration', $device, TimeWindow::parse('1 day')),
                'login_failure' => $this->velocity->count('login_failure', $device, TimeWindow::parse('1 hour')),
            ],
            $trusted,
            $geo,
            null,
            $previous?->country,
            $previous?->ipHash?->value,
            $previous?->sessionIdentifier,
        ));
        $tRisk       = (hrtime(true) - $tRisk0) / 1e6;
        $observation = $observation->withRiskScore($assessment->score());
        $this->observations->save($observation);
        $this->metrics->increment('device_intelligence.observations');
        if ($assessment->isHigh()) {
            $this->metrics->increment('device_intelligence.high_risk');
        }
        $total = (hrtime(true) - $t0) / 1e6;
        $this->metrics->timing('device_intelligence.processing_time', $total);

        return new Analysis(
            $device,
            $match,
            $assessment,
            $observation,
            $normalized,
            $degraded,
            [
                'normalize'  => $tNormalize,
                'candidates' => $tCand,
                'match'      => $tMatch,
                'risk'       => $tRisk,
                'total'      => $total,
            ],
        );
    }

    private function emaStability(Device $device, DeviceMatch $match): Stability
    {
        $changedMass = 0.0;
        $total       = max(1, count($match->changedSignals()) + count($match->stableSignals()));
        $changedMass = count($match->changedSignals()) / $total;
        $next        = 0.88 * $device->stability() + 0.12 * (1 - $changedMass);
        if ($device->observationCount < 3) {
            $next = min($next, 0.7);
        }

        return Stability::clamp($next);
    }

    private function uaFamily(SignalBag $bag): ?string
    {
        $s = $bag->get(SignalName::ClientHints)
            ?? $bag->get(SignalName::UserAgent);
        if ($s === null) {
            return null;
        }
        if (is_array($s->normalizedValue)) {
            return (string) ($s->normalizedValue['browser'] ?? null);
        }

        return (string) $s->normalizedValue;
    }
}
