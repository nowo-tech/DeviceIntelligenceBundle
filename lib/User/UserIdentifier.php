<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\User;

use Nowo\DeviceIntelligence\Exception\InvalidValueException;
use Stringable;

/**
 * Application-agnostic user handle. Never a Doctrine association.
 *
 * Kept byte for byte and compared with `===` (REQ-SEC-006): `Jose`, `josé` and `jose ` are three different users. Since
 * 1.9.2 an unusable value is refused instead of trimmed: empty, longer than {@see MAX_LENGTH} bytes, invalid UTF-8,
 * edge whitespace, control (`Cc`) or format (`Cf`: ZWSP, BOM, bidi marks) characters, and any non-ASCII space (NBSP,
 * line / paragraph separators).
 */
final readonly class UserIdentifier implements Stringable
{
    /** Longest identifier, in bytes (the `user_identifier` columns are VARCHAR(191)). */
    public const MAX_LENGTH = 191;

    private const UNUSABLE = '/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]|(?! )\p{Zs}|\A\s|\s\z/u';

    public string $value;

    public function __construct(string $value)
    {
        if ('' === $value || \strlen($value) > self::MAX_LENGTH) {
            throw new InvalidValueException(\sprintf('User identifier must be 1..%d bytes.', self::MAX_LENGTH));
        }
        if (!mb_check_encoding($value, 'UTF-8') || 1 === preg_match(self::UNUSABLE, $value)) {
            throw new InvalidValueException('User identifier must be valid UTF-8 without edge whitespace, control, format or non-ASCII space characters.');
        }
        $this->value = $value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
