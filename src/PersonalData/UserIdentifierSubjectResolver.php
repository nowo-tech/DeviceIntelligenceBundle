<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\PersonalData;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Default {@see DeviceIntelligencePersonalDataSubjectResolverInterface} (1.9): the original identifier and the current
 * `getUserIdentifier()` (for a {@see UserInterface}), each once; values that cannot be stored are skipped. Since 1.9.3
 * an identifier 1.9.2 refuses still reaches the rows earlier releases stored for it (raw and trimmed, byte for byte;
 * {@see DeviceIntelligencePersonalDataSubject::forPersonalDataRequest()}), so erasure and export do not miss them.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class UserIdentifierSubjectResolver implements DeviceIntelligencePersonalDataSubjectResolverInterface
{
    public function subjectsFor(object $user, ?string $originalIdentifier): array
    {
        $values = [$originalIdentifier];
        if ($user instanceof UserInterface) {
            $values[] = $user->getUserIdentifier();
        }

        $subjects = [];
        foreach ($values as $value) {
            if (null === $value) {
                continue;
            }

            foreach (DeviceIntelligencePersonalDataSubject::forPersonalDataRequest($value) as $subject) {
                $subjects[$subject->userIdentifier] = $subject;
            }
        }

        return array_values($subjects);
    }
}
