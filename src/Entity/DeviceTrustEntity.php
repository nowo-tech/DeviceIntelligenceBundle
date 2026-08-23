<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Explicit user-granted device trust. Never granted automatically on login.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[ORM\Entity]
#[ORM\Table(name: 'device_trust')]
#[ORM\UniqueConstraint(name: 'uniq_di_device_trust', columns: ['device_id', 'user_identifier'])]
#[ORM\Index(name: 'idx_di_device_trust_user', columns: ['user_identifier'])]
final class DeviceTrustEntity
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
    private \DateTimeImmutable $trustedAt;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(name: 'revoked_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(name: 'label', type: Types::STRING, length: 191)]
    private string $label = '';

    #[ORM\Column(name: 'granted_by', type: Types::STRING, length: 32)]
    private string $grantedBy = 'user';

    public function __construct()
    {
        $this->trustedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
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

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(string $userIdentifier): void
    {
        $this->userIdentifier = $userIdentifier;
    }

    public function getTrustedAt(): \DateTimeImmutable
    {
        return $this->trustedAt;
    }

    public function setTrustedAt(\DateTimeImmutable $trustedAt): void
    {
        $this->trustedAt = $trustedAt;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?\DateTimeImmutable $revokedAt): void
    {
        $this->revokedAt = $revokedAt;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getGrantedBy(): string
    {
        return $this->grantedBy;
    }

    public function setGrantedBy(string $grantedBy): void
    {
        $this->grantedBy = $grantedBy;
    }
}
