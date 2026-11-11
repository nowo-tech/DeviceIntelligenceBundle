<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\PersonalData;

/**
 * SPI of the UserKit bridge (1.9): which subjects stand for a host user. Bind your own with
 * `nowo_device_intelligence.personal_data.subject_resolver` when the bundle stored another value than the user
 * identifier (for instance a former identifier the host keeps elsewhere).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface DeviceIntelligencePersonalDataSubjectResolverInterface
{
    /**
     * The subjects of the user; the bridge erases / exports each distinct one once.
     *
     * @param string|null $originalIdentifier the user identifier before any eraser ran (UserKit ≥ 1.12), when known
     *
     * @return list<DeviceIntelligencePersonalDataSubject>
     */
    public function subjectsFor(object $user, ?string $originalIdentifier): array;
}
