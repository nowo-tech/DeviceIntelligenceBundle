<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\PersonalData;

/**
 * Rows deleted by one {@see DeviceIntelligencePersonalDataEraserInterface::erase()} call (1.9, GDPR ports).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class DeviceIntelligenceErasureResult
{
    /**
     * @param int $observations the person's observations (raw user agent, IP hash, session id, signals)
     * @param int $deviceUsers  the person's device ↔ user links (`device_user`)
     * @param int $deviceTrusts the person's trust grants (`device_trust`)
     * @param int $devices      devices of the person that nothing else referenced any more (`device`)
     */
    public function __construct(
        public int $observations = 0,
        public int $deviceUsers = 0,
        public int $deviceTrusts = 0,
        public int $devices = 0,
    ) {
    }

    /**
     * Counts per kind, with stable snake_case keys.
     *
     * @return array{observations: int, device_users: int, device_trusts: int, devices: int}
     */
    public function toArray(): array
    {
        return [
            'observations' => $this->observations,
            'device_users' => $this->deviceUsers,
            'device_trusts' => $this->deviceTrusts,
            'devices' => $this->devices,
        ];
    }

    /**
     * Rows deleted in all.
     */
    public function total(): int
    {
        return $this->observations + $this->deviceUsers + $this->deviceTrusts + $this->devices;
    }
}
