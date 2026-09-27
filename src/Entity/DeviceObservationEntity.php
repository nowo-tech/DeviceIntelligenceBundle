<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Entity;

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
    private \DateTimeImmutable $createdAt;

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
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->id = $id;
    }

    public function getDeviceId(): string
    {
        return $this->deviceId;
    }

    public function setDeviceId(string $deviceId): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->deviceId = $deviceId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->createdAt = $createdAt;
    }

    public function getSchemaVersion(): int
    {
        return $this->schemaVersion;
    }

    public function setSchemaVersion(int $schemaVersion): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->schemaVersion = $schemaVersion;
    }

    public function getSdkVersion(): ?string
    {
        return $this->sdkVersion;
    }

    public function setSdkVersion(?string $sdkVersion): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->sdkVersion = $sdkVersion;
    }

    public function getIpHash(): ?string
    {
        return $this->ipHash;
    }

    public function setIpHash(?string $ipHash): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->ipHash = $ipHash;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->country = $country;
    }

    public function getUserAgentFamily(): ?string
    {
        return $this->userAgentFamily;
    }

    public function setUserAgentFamily(?string $userAgentFamily): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->userAgentFamily = $userAgentFamily;
    }

    public function getRawUserAgent(): ?string
    {
        return $this->rawUserAgent;
    }

    public function setRawUserAgent(?string $rawUserAgent): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->rawUserAgent = $rawUserAgent;
    }

    public function getSessionIdentifier(): ?string
    {
        return $this->sessionIdentifier;
    }

    public function setSessionIdentifier(?string $sessionIdentifier): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->sessionIdentifier = $sessionIdentifier;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(?string $userIdentifier): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
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
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->signals = $signals;
    }

    public function getRiskScore(): int
    {
        return $this->riskScore;
    }

    public function setRiskScore(int $riskScore): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->riskScore = $riskScore;
    }

    public function isDegraded(): bool
    {
        return $this->degraded;
    }

    public function setDegraded(bool $degraded): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->degraded = $degraded;
    }

    public function getEnhancementLevel(): int
    {
        return $this->enhancementLevel;
    }

    public function setEnhancementLevel(int $enhancementLevel): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->enhancementLevel = $enhancementLevel;
    }
}
