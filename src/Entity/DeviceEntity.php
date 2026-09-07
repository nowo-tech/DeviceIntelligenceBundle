<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Persisted device identity. Table prefix is applied by TablePrefixSubscriber.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[ORM\Entity]
#[ORM\Table(name: 'device')]
#[ORM\Index(name: 'idx_di_device_os_browser', columns: ['os_family', 'browser_family'])]
#[ORM\Index(name: 'idx_di_device_last_seen', columns: ['last_seen_at'])]
final class DeviceEntity
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 26, options: ['fixed' => true])]
    private string $id = '';

    #[ORM\Column(name: 'first_seen_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $firstSeenAt;

    #[ORM\Column(name: 'last_seen_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lastSeenAt;

    #[ORM\Column(name: 'observation_count', type: Types::INTEGER)]
    private int $observationCount = 0;

    #[ORM\Column(name: 'confidence', type: Types::FLOAT)]
    private float $confidence = 0.5;

    #[ORM\Column(name: 'stability', type: Types::FLOAT)]
    private float $stability = 0.5;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 16)]
    private string $status = 'active';

    #[ORM\Column(name: 'os_family', type: Types::STRING, length: 32)]
    private string $osFamily = 'other';

    #[ORM\Column(name: 'browser_family', type: Types::STRING, length: 32)]
    private string $browserFamily = 'other';

    #[ORM\Column(name: 'gpu_family', type: Types::STRING, length: 64)]
    private string $gpuFamily = 'other';

    #[ORM\Column(name: 'screen_class', type: Types::STRING, length: 32)]
    private string $screenClass = 'other';

    #[ORM\Column(name: 'timezone', type: Types::STRING, length: 64)]
    private string $timezone = 'UTC';

    #[ORM\Column(name: 'blocking_key', type: Types::STRING, length: 16)]
    private string $blockingKey = '';

    #[ORM\Column(name: 'label', type: Types::STRING, length: 191)]
    private string $label = '';

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'metadata', type: Types::JSON)]
    private array $metadata = [];

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'last_signals', type: Types::JSON)]
    private array $lastSignals = [];

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->firstSeenAt = $now;
        $this->lastSeenAt = $now;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getFirstSeenAt(): \DateTimeImmutable
    {
        return $this->firstSeenAt;
    }

    public function setFirstSeenAt(\DateTimeImmutable $firstSeenAt): void
    {
        $this->firstSeenAt = $firstSeenAt;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(\DateTimeImmutable $lastSeenAt): void
    {
        $this->lastSeenAt = $lastSeenAt;
    }

    public function getObservationCount(): int
    {
        return $this->observationCount;
    }

    public function setObservationCount(int $observationCount): void
    {
        $this->observationCount = $observationCount;
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }

    public function setConfidence(float $confidence): void
    {
        $this->confidence = $confidence;
    }

    public function getStability(): float
    {
        return $this->stability;
    }

    public function setStability(float $stability): void
    {
        $this->stability = $stability;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getOsFamily(): string
    {
        return $this->osFamily;
    }

    public function setOsFamily(string $osFamily): void
    {
        $this->osFamily = $osFamily;
    }

    public function getBrowserFamily(): string
    {
        return $this->browserFamily;
    }

    public function setBrowserFamily(string $browserFamily): void
    {
        $this->browserFamily = $browserFamily;
    }

    public function getGpuFamily(): string
    {
        return $this->gpuFamily;
    }

    public function setGpuFamily(string $gpuFamily): void
    {
        $this->gpuFamily = $gpuFamily;
    }

    public function getScreenClass(): string
    {
        return $this->screenClass;
    }

    public function setScreenClass(string $screenClass): void
    {
        $this->screenClass = $screenClass;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): void
    {
        $this->timezone = $timezone;
    }

    public function getBlockingKey(): string
    {
        return $this->blockingKey;
    }

    public function setBlockingKey(string $blockingKey): void
    {
        $this->blockingKey = $blockingKey;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLastSignals(): array
    {
        return $this->lastSignals;
    }

    /**
     * @param array<string, mixed> $lastSignals
     */
    public function setLastSignals(array $lastSignals): void
    {
        $this->lastSignals = $lastSignals;
    }
}
