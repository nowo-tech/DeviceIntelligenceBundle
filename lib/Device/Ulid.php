<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Device;

use DateTimeInterface;
use Nowo\DeviceIntelligence\Exception\InvalidValueException;

use function ord;
use function strlen;

use const STR_PAD_LEFT;
use const STR_PAD_RIGHT;

/**
 * Crockford Base32 ULID (26 chars). Time-sortable, no hyphens.
 */
final readonly class Ulid
{
    private const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function generate(DateTimeInterface $now): string
    {
        $ms        = ((int) $now->format('U')) * 1000 + (int) $now->format('v');
        $time      = pack('J', $ms);
        $timeBytes = substr($time, 2);
        $random    = random_bytes(10);
        $bytes     = $timeBytes . $random;

        return self::encode($bytes);
    }

    public static function isValid(string $value): bool
    {
        return preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $value) === 1;
    }

    /**
     * @param non-empty-string $bytes 16 bytes
     */
    private static function encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) !== 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $out .= self::ALPHABET[(int) bindec($chunk)];
        }

        $out = substr($out, 0, 26);
        // 16 bytes always encode to at least 26 Crockford chars; this is a defensive invariant.
        if (strlen($out) !== 26) { // @codeCoverageIgnore
            throw new InvalidValueException('Failed to encode ULID.'); // @codeCoverageIgnore
        }

        return $out;
    }
}
