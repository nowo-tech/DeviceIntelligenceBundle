<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\PersonalData;

use InvalidArgumentException;
use Nowo\DeviceIntelligence\Exception\InvalidValueException;
use Nowo\DeviceIntelligence\User\UserIdentifier;

/**
 * The person whose device data is erased or exported (1.9, GDPR ports): the user identifier the bundle stores in the
 * `user_identifier` column of `observation`, `device_user` and `device_trust`.
 *
 * The value must be usable exactly as the bundle requires it on write ({@see UserIdentifier}, 1.9.2: 1..191 bytes, no
 * edge whitespace, no control, format or non-ASCII space characters, no case folding); it is never trimmed, so
 * ` alice` never erases `alice`. Rows are matched byte for byte, whatever the column collation (REQ-SEC-006).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class DeviceIntelligencePersonalDataSubject
{
    /** Length of the `user_identifier` columns. */
    public const MAX_LENGTH = 191;

    private function __construct(
        public string $userIdentifier,
    ) {
    }

    /**
     * The subject of the given user identifier (as `UserInterface::getUserIdentifier()` returns it).
     *
     * @throws InvalidArgumentException when the identifier is not a usable {@see UserIdentifier} (NUL byte included)
     */
    public static function fromUserIdentifier(string $userIdentifier): self
    {
        if (str_contains($userIdentifier, "\0")) {
            throw new InvalidArgumentException('The user identifier must not contain a NUL byte.');
        }

        try {
            return new self((new UserIdentifier($userIdentifier))->value);
        } catch (InvalidValueException $error) {
            throw new InvalidArgumentException(\sprintf('The user identifier must hold 1..%d bytes, without edge whitespace, control, format or non-ASCII space characters.', self::MAX_LENGTH), 0, $error);
        }
    }
}
