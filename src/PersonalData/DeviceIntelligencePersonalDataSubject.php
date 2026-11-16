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
 * GDPR requests (1.9.3, audit RE1-L1) use {@see forPersonalDataRequest()}: it also reaches the rows that releases
 * before 1.9.2 stored for identifiers 1.9.2 refuses, still byte for byte (never a look-alike).
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

    /**
     * The subjects a GDPR erase or export request matches for a user identifier (1.9.3, audit RE1-L1).
     *
     * A usable identifier gives exactly {@see fromUserIdentifier()}. An identifier 1.9.2 refuses can still own rows
     * written before 1.9.2, which trimmed (PHP `trim()`) and accepted internal NBSP, ZWSP, bidi marks or tabs: the
     * request then matches the raw value and its trimmed variant, each byte for byte. Only values that can be stored
     * at all are kept (1..191 bytes, valid UTF-8, no NUL byte); a blank identifier matches nothing. Use it for erasure and export only; reads and writes
     * keep the strict {@see fromUserIdentifier()} rules.
     *
     * @return list<self> one subject per distinct value; empty when nothing can match
     */
    public static function forPersonalDataRequest(string $userIdentifier): array
    {
        try {
            return [self::fromUserIdentifier($userIdentifier)];
        } catch (InvalidArgumentException) {
            // Refused since 1.9.2: look for what earlier releases stored.
        }

        $trimmed = trim($userIdentifier);
        if ('' === $trimmed) {
            return []; // never storable, before or after 1.9.2
        }

        $subjects = [];
        foreach ([$userIdentifier, $trimmed] as $value) {
            if (\strlen($value) > self::MAX_LENGTH || str_contains($value, "\0") || !mb_check_encoding($value, 'UTF-8')) {
                continue;
            }
            $subjects[$value] = new self($value);
        }

        return array_values($subjects);
    }
}
