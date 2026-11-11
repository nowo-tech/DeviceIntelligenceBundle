<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\PersonalData\UserKit;

use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataExporterInterface;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataSubjectResolverInterface;
use Nowo\UserKitBundle\PersonalData\PersonalDataExportProviderInterface;

/**
 * UserKit export section `devices` (1.9; registered when nowo-tech/user-kit-bundle is installed,
 * `personal_data.enabled` is true and the exporter port is bound): the devices of every subject the resolver returns
 * for the user (default: the current user identifier), see {@see DeviceIntelligencePersonalDataExporterInterface}.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class DeviceIntelligenceUserKitExportProvider implements PersonalDataExportProviderInterface
{
    public const SECTION = 'devices';

    public const PRIORITY = 35;

    public function __construct(
        private DeviceIntelligencePersonalDataExporterInterface $exporter,
        private DeviceIntelligencePersonalDataSubjectResolverInterface $subjects,
    ) {
    }

    public function getSectionKey(): string
    {
        return self::SECTION;
    }

    public function supports(object $user): bool
    {
        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function export(object $user): array
    {
        $rows = [];
        foreach ($this->subjects->subjectsFor($user, null) as $subject) {
            array_push($rows, ...$this->exporter->export($subject));
        }

        return $rows;
    }
}
