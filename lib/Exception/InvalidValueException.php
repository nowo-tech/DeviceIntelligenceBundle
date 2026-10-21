<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Exception;

use InvalidArgumentException;

/**
 * Thrown when a value object receives an out-of-range or malformed value.
 */
final class InvalidValueException extends InvalidArgumentException
{
}
