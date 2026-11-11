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
 * The value is normalised exactly as the bundle normalises it on write ({@see UserIdentifier}: surrounding whitespace
 * trimmed, no case folding), then it must hold 1..191 bytes (the column length) and no NUL byte (PDO cuts bound values
 * at NUL). Rows are matched byte for byte, whatever the column collation.
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
     * @throws InvalidArgumentException when the identifier is empty, longer than 191 bytes or holds a NUL byte
     */
    public static function fromUserIdentifier(string $userIdentifier): self
    {
        if (str_contains($userIdentifier, "\0")) {
            throw new InvalidArgumentException('The user identifier must not contain a NUL byte.');
        }

        try {
            return new self((new UserIdentifier($userIdentifier))->value);
        } catch (InvalidValueException $error) {
            throw new InvalidArgumentException(\sprintf('The user identifier must hold 1..%d bytes once trimmed.', self::MAX_LENGTH), 0, $error);
        }
    }
}
