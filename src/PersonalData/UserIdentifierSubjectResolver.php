<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\PersonalData;

use InvalidArgumentException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Default {@see DeviceIntelligencePersonalDataSubjectResolverInterface} (1.9): the original identifier and the current
 * `getUserIdentifier()` (for a {@see UserInterface}), each once; values that cannot be stored are skipped.
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

            try {
                $subject = DeviceIntelligencePersonalDataSubject::fromUserIdentifier($value);
            } catch (InvalidArgumentException) {
                continue;
            }
            $subjects[$subject->userIdentifier] = $subject;
        }

        return array_values($subjects);
    }
}
