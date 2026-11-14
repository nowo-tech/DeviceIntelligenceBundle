<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Exception;

/**
 * A write was refused because the storage still holds a row of a look-alike identifier (REQ-SEC-006, 1.9.2).
 *
 * Raised when a unique key on a folding collation (`josé@` = `jose@`, `jose@ ` = `jose@`) rejects the row of an
 * identifier that differs byte for byte from the stored one. The write fails closed: the look-alike's row is never
 * updated in its place. Running the `ALTER` of UPGRADING 1.9.2 (binary NO PAD collation) lets both rows coexist.
 */
final class LookAlikeIdentifierException extends DeviceIntelligenceException
{
}
