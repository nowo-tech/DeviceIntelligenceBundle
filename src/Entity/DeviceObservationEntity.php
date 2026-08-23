<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Persisted observation snapshot. Signals are compact derived values, never raw captures.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[ORM\Entity]
#[ORM\Table(name: 'observation')]
#[ORM\Index(name: 'idx_di_obs_device_created', columns: ['device_id', 'created_at'])]
final class DeviceObservationEntity
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 26, options: ['fixed' => true])]
    private string $id = '';

    #[ORM\Column(name: 'device_id', type: Types::STRING, length: 26, options: ['fixed' => true])]
    private string $deviceId = '';

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'schema_version', type: Types::INTEGER)]
    private int $schemaVersion = 1;

    #[ORM\Column(name: 'sdk_version', type: Types::STRING, length: 32, nullable: true)]
    private ?string $sdkVersion = null;

    #[ORM\Column(name: 'ip_hash', type: Types::STRING, length: 64, nullable: true)]
    private ?string $ipHash = null;

    #[ORM\Column(name: 'country', type: Types::STRING, length: 8, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(name: 'user_agent_family', type: Types::STRING, length: 64, nullable: true)]
    private ?string $userAgentFamily = null;

    #[ORM\Column(name: 'raw_user_agent', type: Types::STRING, length: 512, nullable: true)]
    private ?string $rawUserAgent = null;

    #[ORM\Column(name: 'session_identifier', type: Types::STRING, length: 128, nullable: true)]
    private ?string $sessionIdentifier = null;

    #[ORM\Column(name: 'user_identifier', type: Types::STRING, length: 191, nullable: true)]
    private ?string $userIdentifier = null;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'signals', type: Types::JSON)]
    private array $signals = [];

    #[ORM\Column(name: 'risk_score', type: Types::INTEGER)]
    private int $riskScore = 0;

    #[ORM\Column(name: 'degraded', type: Types::BOOLEAN)]
    private bool $degraded = false;

    #[ORM\Column(name: 'enhancement_level', type: Types::INTEGER)]
    private int $enhancementLevel = 0;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getDeviceId(): string
    {
        return $this->deviceId;
    }

    public function setDeviceId(string $deviceId): void
    {
        $this->deviceId = $deviceId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getSchemaVersion(): int
    {
        return $this->schemaVersion;
    }

    public function setSchemaVersion(int $schemaVersion): void
    {
        $this->schemaVersion = $schemaVersion;
    }

    public function getSdkVersion(): ?string
    {
        return $this->sdkVersion;
    }

    public function setSdkVersion(?string $sdkVersion): void
    {
        $this->sdkVersion = $sdkVersion;
    }

    public function getIpHash(): ?string
    {
        return $this->ipHash;
    }

    public function setIpHash(?string $ipHash): void
    {
        $this->ipHash = $ipHash;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): void
    {
        $this->country = $country;
    }

    public function getUserAgentFamily(): ?string
    {
        return $this->userAgentFamily;
    }

    public function setUserAgentFamily(?string $userAgentFamily): void
    {
        $this->userAgentFamily = $userAgentFamily;
    }

    public function getRawUserAgent(): ?string
    {
        return $this->rawUserAgent;
    }

    public function setRawUserAgent(?string $rawUserAgent): void
    {
        $this->rawUserAgent = $rawUserAgent;
    }

    public function getSessionIdentifier(): ?string
    {
        return $this->sessionIdentifier;
    }

    public function setSessionIdentifier(?string $sessionIdentifier): void
    {
        $this->sessionIdentifier = $sessionIdentifier;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(?string $userIdentifier): void
    {
        $this->userIdentifier = $userIdentifier;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSignals(): array
    {
        return $this->signals;
    }

    /**
     * @param array<string, mixed> $signals
     */
    public function setSignals(array $signals): void
    {
        $this->signals = $signals;
    }

    public function getRiskScore(): int
    {
        return $this->riskScore;
    }

    public function setRiskScore(int $riskScore): void
    {
        $this->riskScore = $riskScore;
    }

    public function isDegraded(): bool
    {
        return $this->degraded;
    }

    public function setDegraded(bool $degraded): void
    {
        $this->degraded = $degraded;
    }

    public function getEnhancementLevel(): int
    {
        return $this->enhancementLevel;
    }

    public function setEnhancementLevel(int $enhancementLevel): void
    {
        $this->enhancementLevel = $enhancementLevel;
    }
}
