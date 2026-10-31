<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Persisted observation snapshot. Signals are compact derived values, never raw captures.
 *
 * Not final: on PHP 8.3 Doctrine generates its lazy proxies by extending the entity (native lazy objects need
 * PHP >= 8.4). Do not extend it in an application; the class is internal to the bundle mapping.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[ORM\Entity]
#[ORM\Table(name: 'observation')]
#[ORM\Index(name: 'idx_di_obs_device_created', columns: ['device_id', 'created_at'])]
class DeviceObservationEntity
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

    /**
     * Creates the device observation entity.
     */
    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    /**
     * Returns the id.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Sets the id.
     */
    public function setId(string $id): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->id = $id;
    }

    /**
     * Returns the device id.
     */
    public function getDeviceId(): string
    {
        return $this->deviceId;
    }

    /**
     * Sets the device id.
     */
    public function setDeviceId(string $deviceId): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->deviceId = $deviceId;
    }

    /**
     * Returns the created at.
     */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Sets the created at.
     */
    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->createdAt = $createdAt;
    }

    /**
     * Returns the schema version.
     */
    public function getSchemaVersion(): int
    {
        return $this->schemaVersion;
    }

    /**
     * Sets the schema version.
     */
    public function setSchemaVersion(int $schemaVersion): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->schemaVersion = $schemaVersion;
    }

    /**
     * Returns the sdk version.
     */
    public function getSdkVersion(): ?string
    {
        return $this->sdkVersion;
    }

    /**
     * Sets the sdk version.
     */
    public function setSdkVersion(?string $sdkVersion): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->sdkVersion = $sdkVersion;
    }

    /**
     * Returns the ip hash.
     */
    public function getIpHash(): ?string
    {
        return $this->ipHash;
    }

    /**
     * Sets the ip hash.
     */
    public function setIpHash(?string $ipHash): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->ipHash = $ipHash;
    }

    /**
     * Returns the country.
     */
    public function getCountry(): ?string
    {
        return $this->country;
    }

    /**
     * Sets the country.
     */
    public function setCountry(?string $country): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->country = $country;
    }

    /**
     * Returns the user agent family.
     */
    public function getUserAgentFamily(): ?string
    {
        return $this->userAgentFamily;
    }

    /**
     * Sets the user agent family.
     */
    public function setUserAgentFamily(?string $userAgentFamily): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->userAgentFamily = $userAgentFamily;
    }

    /**
     * Returns the raw user agent.
     */
    public function getRawUserAgent(): ?string
    {
        return $this->rawUserAgent;
    }

    /**
     * Sets the raw user agent.
     */
    public function setRawUserAgent(?string $rawUserAgent): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->rawUserAgent = $rawUserAgent;
    }

    /**
     * Returns the session identifier.
     */
    public function getSessionIdentifier(): ?string
    {
        return $this->sessionIdentifier;
    }

    /**
     * Sets the session identifier.
     */
    public function setSessionIdentifier(?string $sessionIdentifier): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->sessionIdentifier = $sessionIdentifier;
    }

    /**
     * Returns the user identifier.
     */
    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    /**
     * Sets the user identifier.
     */
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

    /**
     * Returns the risk score.
     */
    public function getRiskScore(): int
    {
        return $this->riskScore;
    }

    /**
     * Sets the risk score.
     */
    public function setRiskScore(int $riskScore): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->riskScore = $riskScore;
    }

    /**
     * Whether it is degraded.
     */
    public function isDegraded(): bool
    {
        return $this->degraded;
    }

    /**
     * Sets the degraded.
     */
    public function setDegraded(bool $degraded): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->degraded = $degraded;
    }

    /**
     * Returns the enhancement level.
     */
    public function getEnhancementLevel(): int
    {
        return $this->enhancementLevel;
    }

    /**
     * Sets the enhancement level.
     */
    public function setEnhancementLevel(int $enhancementLevel): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->enhancementLevel = $enhancementLevel;
    }
}
