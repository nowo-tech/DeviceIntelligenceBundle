<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Device ↔ user relation. Unique on (device_id, user_identifier).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[ORM\Entity]
#[ORM\Table(name: 'device_user')]
#[ORM\UniqueConstraint(name: 'uniq_di_device_user', columns: ['device_id', 'user_identifier'])]
#[ORM\Index(name: 'idx_di_device_user_user', columns: ['user_identifier'])]
final class DeviceUserEntity
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'device_id', type: Types::STRING, length: 26, options: ['fixed' => true])]
    private string $deviceId = '';

    #[ORM\Column(name: 'user_identifier', type: Types::STRING, length: 191)]
    private string $userIdentifier = '';

    #[ORM\Column(name: 'first_seen_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $firstSeenAt;

    #[ORM\Column(name: 'last_seen_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $lastSeenAt;

    #[ORM\Column(name: 'login_count', type: Types::INTEGER)]
    private int $loginCount = 0;

    /**
     * Creates the device user entity.
     */
    public function __construct()
    {
        $now = new DateTimeImmutable();
        $this->firstSeenAt = $now;
        $this->lastSeenAt = $now;
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
     * Returns the first seen at.
     */
    public function getFirstSeenAt(): DateTimeImmutable
    {
        return $this->firstSeenAt;
    }

    /**
     * Sets the first seen at.
     */
    public function setFirstSeenAt(DateTimeImmutable $firstSeenAt): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->firstSeenAt = $firstSeenAt;
    }

    /**
     * Returns the last seen at.
     */
    public function getLastSeenAt(): DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    /**
     * Sets the last seen at.
     */
    public function setLastSeenAt(DateTimeImmutable $lastSeenAt): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->lastSeenAt = $lastSeenAt;
    }

    /**
     * Returns the login count.
     */
    public function getLoginCount(): int
    {
        return $this->loginCount;
    }

    /**
     * Sets the login count.
     */
    public function setLoginCount(int $loginCount): void
    {
        // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service
        $this->loginCount = $loginCount;
    }
}
