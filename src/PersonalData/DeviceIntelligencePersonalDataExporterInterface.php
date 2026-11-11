<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\PersonalData;

/**
 * Exports one person's device data (1.9, GDPR art. 15 / 20): one entry per device linked to or trusted by the person.
 *
 * Each entry holds `first_seen_at`, `last_seen_at` (ATOM strings, null for a device only trusted), `sign_ins` (int),
 * `os_family`, `browser_family` (string, null when the device row is gone), `trusted` (bool: granted, not revoked and
 * not expired), `trusted_at` and `trust_expires_at` (ATOM strings or null). No device ids, hashes, fingerprints or
 * signals. Rows are matched byte for byte on the stored user identifier.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface DeviceIntelligencePersonalDataExporterInterface
{
    /**
     * The subject's devices, oldest link first.
     *
     * @return list<array{first_seen_at: string|null, last_seen_at: string|null, sign_ins: int, os_family: string|null, browser_family: string|null, trusted: bool, trusted_at: string|null, trust_expires_at: string|null}>
     */
    public function export(DeviceIntelligencePersonalDataSubject $subject): array;
}
