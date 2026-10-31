<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Explicit user-granted device trust. Never granted automatically on login.
 *
 * Not final: on PHP 8.3 Doctrine generates its lazy proxies by extending the entity (native lazy objects need
 * PHP >= 8.4). Do not extend it in an application; the class is internal to the bundle mapping.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[ORM\Entity]
#[ORM\Table(name: 'device_trust')]
#[ORM\UniqueConstraint(name: 'uniq_di_device_trust', columns: ['device_id', 'user_identifier'])]
#[ORM\Index(name: 'idx_di_device_trust_user', columns: ['user_identifier'])]
class DeviceTrustEntity
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'device_id', type: Types::STRING, length: 26, options: ['fixed' => true])]
    private string $deviceId = '';

    #[ORM\Column(name: 'user_identifier', type: Types::STRING, length: 191)]
    private string $userIdentifier = '';

    #[ORM\Column(name: 'trusted_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $trustedAt;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $expiresAt = null;

    #[ORM\Column(name: 'revoked_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $revokedAt = null;

    #[ORM\Column(name: 'label', type: Types::STRING, length: 191)]
    private string $label = '';

    #[ORM\Column(name: 'granted_by', type: Types::STRING, length: 32)]
    private string $grantedBy = 'user';

    /**
     * Creates the device trust entity.
     */
    public function __construct()
    {
        $this->trustedAt = new DateTimeImmutable();
    }

    /**
     * Returns the id.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Sets the id.
     */
    public function setId(?int $id): void
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
     * Returns the user identifier.
     */
    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    /**
     * Sets the user identifier.
     */
    public function setUserIdentifier(string $userIdentifier): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->userIdentifier = $userIdentifier;
    }

    /**
     * Returns the trusted at.
     */
    public function getTrustedAt(): DateTimeImmutable
    {
        return $this->trustedAt;
    }

    /**
     * Sets the trusted at.
     */
    public function setTrustedAt(DateTimeImmutable $trustedAt): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->trustedAt = $trustedAt;
    }

    /**
     * Returns the expires at.
     */
    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * Sets the expires at.
     */
    public function setExpiresAt(?DateTimeImmutable $expiresAt): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->expiresAt = $expiresAt;
    }

    /**
     * Returns the revoked at.
     */
    public function getRevokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    /**
     * Sets the revoked at.
     */
    public function setRevokedAt(?DateTimeImmutable $revokedAt): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->revokedAt = $revokedAt;
    }

    /**
     * Returns the label.
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Sets the label.
     */
    public function setLabel(string $label): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->label = $label;
    }

    /**
     * Returns the granted by.
     */
    public function getGrantedBy(): string
    {
        return $this->grantedBy;
    }

    /**
     * Sets the granted by.
     */
    public function setGrantedBy(string $grantedBy): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->grantedBy = $grantedBy;
    }
}
