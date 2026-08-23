<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\User;

use Nowo\DeviceIntelligence\Exception\InvalidValueException;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligence\User\UserIdentifierResolverInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Resolves the application user via UserInterface::getUserIdentifier().
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SecurityUserIdentifierResolver implements UserIdentifierResolverInterface
{
    public function resolve(object $user): UserIdentifier
    {
        if ($user instanceof UserInterface) {
            return new UserIdentifier($user->getUserIdentifier());
        }
        if (method_exists($user, 'getUserIdentifier')) {
            $value = $user->getUserIdentifier();
            if (\is_string($value)) {
                return new UserIdentifier($value);
            }
        }

        throw new InvalidValueException('Cannot resolve a user identifier from '.$user::class.'.');
    }
}
