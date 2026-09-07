<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Doctrine;

use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Device\DeviceStatus;
use Nowo\DeviceIntelligence\Device\Stability;
use Nowo\DeviceIntelligence\Matching\CandidateIndexKey;
use Nowo\DeviceIntelligence\Matching\Confidence;
use Nowo\DeviceIntelligence\Observation\DeviceObservation;
use Nowo\DeviceIntelligence\Observation\ObservationId;
use Nowo\DeviceIntelligence\Privacy\IpHash;
use Nowo\DeviceIntelligence\Signal\EntropyCategory;
use Nowo\DeviceIntelligence\Signal\Quality;
use Nowo\DeviceIntelligence\Signal\Signal;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligence\Signal\SignalName;
use Nowo\DeviceIntelligence\Signal\SignalSource;
use Nowo\DeviceIntelligence\Trust\TrustedDevice;
use Nowo\DeviceIntelligence\User\DeviceUserRelation;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceEntity;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceObservationEntity;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceTrustEntity;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceUserEntity;

/**
 * Maps core value objects to Doctrine entities and back. Pure, no EntityManager.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DeviceMapper
{
    public function toDeviceEntity(Device $device, ?DeviceEntity $entity = null): DeviceEntity
    {
        $entity ??= new DeviceEntity();
        $entity->setId($device->id->value);
        $entity->setFirstSeenAt($device->firstSeenAt);
        $entity->setLastSeenAt($device->lastSeenAt);
        $entity->setObservationCount($device->observationCount);
        $entity->setConfidence($device->confidence->value);
        $entity->setStability($device->stability());
        $entity->setStatus($device->status->value);
        $entity->setOsFamily($device->indexKey->osFamily);
        $entity->setBrowserFamily($device->indexKey->browserFamily);
        $entity->setGpuFamily($device->indexKey->gpuFamily);
        $entity->setScreenClass($device->indexKey->screenClass);
        $entity->setTimezone($device->indexKey->timezone);
        $entity->setBlockingKey($device->indexKey->blockingKey);
        $entity->setLabel($device->label);
        $entity->setMetadata($device->metadata);
        $entity->setLastSignals($device->lastSignals->toArray());

        return $entity;
    }

    public function toDevice(DeviceEntity $entity): Device
    {
        return new Device(
            new DeviceId($entity->getId()),
            $entity->getFirstSeenAt(),
            $entity->getLastSeenAt(),
            $entity->getObservationCount(),
            new Confidence($entity->getConfidence()),
            new Stability($entity->getStability()),
            DeviceStatus::from($entity->getStatus()),
            new CandidateIndexKey(
                $entity->getOsFamily(),
                $entity->getBrowserFamily(),
                $entity->getGpuFamily(),
                $entity->getScreenClass(),
                $entity->getTimezone(),
                $entity->getBlockingKey(),
            ),
            $entity->getLabel(),
            $entity->getMetadata(),
            $this->signalsFromArray($entity->getLastSignals()),
        );
    }

    public function toObservationEntity(DeviceObservation $observation, ?DeviceObservationEntity $entity = null): DeviceObservationEntity
    {
        $entity ??= new DeviceObservationEntity();
        $entity->setId($observation->id->value);
        $entity->setDeviceId($observation->deviceId->value);
        $entity->setCreatedAt($observation->createdAt);
        $entity->setSchemaVersion($observation->schemaVersion);
        $entity->setSdkVersion($observation->sdkVersion);
        $entity->setIpHash($observation->ipHash?->value);
        $entity->setCountry($observation->country);
        $entity->setUserAgentFamily($observation->userAgentFamily);
        $entity->setRawUserAgent($observation->rawUserAgent);
        $entity->setSessionIdentifier($observation->sessionIdentifier);
        $entity->setUserIdentifier($observation->userIdentifier?->value);
        $entity->setSignals($observation->signals->toArray());
        $entity->setRiskScore($observation->riskScore);
        $entity->setDegraded($observation->degraded);
        $entity->setEnhancementLevel($observation->enhancementLevel);

        return $entity;
    }

    public function toObservation(DeviceObservationEntity $entity): DeviceObservation
    {
        $ipHash = null;
        if (null !== $entity->getIpHash() && '' !== $entity->getIpHash()) {
            $ipHash = new IpHash($entity->getIpHash());
        }
        $user = null;
        if (null !== $entity->getUserIdentifier() && '' !== $entity->getUserIdentifier()) {
            $user = new UserIdentifier($entity->getUserIdentifier());
        }

        return new DeviceObservation(
            new ObservationId($entity->getId()),
            new DeviceId($entity->getDeviceId()),
            $entity->getCreatedAt(),
            $entity->getSchemaVersion(),
            $entity->getSdkVersion(),
            $ipHash,
            $entity->getCountry(),
            $entity->getUserAgentFamily(),
            $entity->getRawUserAgent(),
            $entity->getSessionIdentifier(),
            $user,
            $this->signalsFromArray($entity->getSignals()),
            $entity->getRiskScore(),
            $entity->isDegraded(),
            $entity->getEnhancementLevel(),
        );
    }

    public function toUserEntity(DeviceUserRelation $relation, ?DeviceUserEntity $entity = null): DeviceUserEntity
    {
        $entity ??= new DeviceUserEntity();
        $entity->setDeviceId($relation->deviceId->value);
        $entity->setUserIdentifier($relation->userIdentifier->value);
        $entity->setFirstSeenAt($relation->firstSeenAt);
        $entity->setLastSeenAt($relation->lastSeenAt);
        $entity->setLoginCount($relation->loginCount);

        return $entity;
    }

    public function toUserRelation(DeviceUserEntity $entity): DeviceUserRelation
    {
        return new DeviceUserRelation(
            new DeviceId($entity->getDeviceId()),
            new UserIdentifier($entity->getUserIdentifier()),
            $entity->getFirstSeenAt(),
            $entity->getLastSeenAt(),
            $entity->getLoginCount(),
        );
    }

    public function toTrustEntity(TrustedDevice $trust, ?DeviceTrustEntity $entity = null): DeviceTrustEntity
    {
        $entity ??= new DeviceTrustEntity();
        $entity->setDeviceId($trust->deviceId->value);
        $entity->setUserIdentifier($trust->userIdentifier->value);
        $entity->setTrustedAt($trust->trustedAt);
        $entity->setExpiresAt($trust->expiresAt);
        $entity->setRevokedAt($trust->revokedAt);
        $entity->setLabel($trust->label);
        $entity->setGrantedBy($trust->grantedBy);

        return $entity;
    }

    public function toTrustedDevice(DeviceTrustEntity $entity): TrustedDevice
    {
        return new TrustedDevice(
            new DeviceId($entity->getDeviceId()),
            new UserIdentifier($entity->getUserIdentifier()),
            $entity->getTrustedAt(),
            $entity->getExpiresAt(),
            $entity->getRevokedAt(),
            $entity->getLabel(),
            $entity->getGrantedBy(),
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return SignalBag
     */
    public function signalsFromArray(array $data): SignalBag
    {
        $bag = SignalBag::empty();
        foreach ($data as $name => $row) {
            if (!\is_string($name) || !\is_array($row)) {
                continue;
            }
            $enum = SignalName::tryFrom($name);
            if (null === $enum) {
                continue;
            }
            $collectedAt = new \DateTimeImmutable();
            if (isset($row['collectedAt']) && \is_string($row['collectedAt'])) {
                $parsed = \DateTimeImmutable::createFromFormat(\DATE_ATOM, $row['collectedAt']);
                if ($parsed instanceof \DateTimeImmutable) {
                    $collectedAt = $parsed;
                }
            }
            $entropy = $enum->entropyCategory();
            if (isset($row['entropyCategory']) && \is_string($row['entropyCategory'])) {
                $entropy = EntropyCategory::tryFrom($row['entropyCategory']) ?? $entropy;
            }
            $source = SignalSource::Client;
            if (isset($row['source']) && \is_string($row['source'])) {
                $source = SignalSource::tryFrom($row['source']) ?? $source;
            }
            $quality = isset($row['quality']) ? (float) $row['quality'] : 1.0;
            $stability = isset($row['stability']) ? (float) $row['stability'] : $enum->expectedStability();
            $bag = $bag->with(new Signal(
                $enum,
                $row['value'] ?? null,
                $row['normalizedValue'] ?? $row['value'] ?? null,
                new Quality(max(0.0, min(1.0, $quality))),
                max(0.0, min(1.0, $stability)),
                $entropy,
                $collectedAt,
                $source,
            ));
        }

        return $bag;
    }
}
