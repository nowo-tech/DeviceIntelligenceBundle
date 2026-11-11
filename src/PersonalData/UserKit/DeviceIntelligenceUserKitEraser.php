<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\PersonalData\UserKit;

use InvalidArgumentException;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataEraserInterface;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataSubject;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataSubjectResolverInterface;
use Nowo\UserKitBundle\PersonalData\ErasureRequest;
use Nowo\UserKitBundle\PersonalData\PersonalDataEraserInterface;

/**
 * UserKit personal-data eraser `device_intelligence` (1.9; registered when nowo-tech/user-kit-bundle is installed,
 * `personal_data.enabled` is true and the eraser port is bound). Priority {@see self::PRIORITY}: it runs before the
 * host's identity eraser, which may rewrite the identifier the rows are keyed by.
 *
 * Subjects: the resolver's (default: the original and the current user identifier) plus `previous_email` from the
 * request context when it is a non-empty string; each distinct one is erased once. Inside UserKit's transaction (same
 * connection) the port joins it and dispatches no event.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class DeviceIntelligenceUserKitEraser implements PersonalDataEraserInterface
{
    public const KEY = 'device_intelligence';

    public const PRIORITY = 150;

    public function __construct(
        private DeviceIntelligencePersonalDataEraserInterface $eraser,
        private DeviceIntelligencePersonalDataSubjectResolverInterface $subjects,
    ) {
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function supports(object $user): bool
    {
        return true;
    }

    public function erase(ErasureRequest $request): void
    {
        // UserKit >= 1.12 captures the identifier before any eraser ran; older versions have no such property.
        $original = get_object_vars($request)['originalIdentifier'] ?? null;
        $subjects = [];
        foreach ($this->subjects->subjectsFor($request->user, \is_string($original) ? $original : null) as $subject) {
            $subjects[$subject->userIdentifier] = $subject;
        }

        $previous = $request->context['previous_email'] ?? null;
        if (\is_string($previous) && '' !== $previous) {
            try {
                $subject = DeviceIntelligencePersonalDataSubject::fromUserIdentifier($previous);
                $subjects[$subject->userIdentifier] = $subject;
            } catch (InvalidArgumentException) {
                // Not a value the bundle could have stored.
            }
        }

        foreach ($subjects as $subject) {
            $this->eraser->erase($subject);
        }
    }
}
