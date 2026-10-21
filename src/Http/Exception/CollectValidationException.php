<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Http\Exception;

use RuntimeException;

/**
 * Invalid collect payload or transport checks.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class CollectValidationException extends RuntimeException
{
    /**
     * Creates the collect validation exception.
     */
    public function __construct(string $message, private int $statusCode = 400)
    {
        parent::__construct($message, $statusCode);
    }

    /**
     * HTTP status code to answer with.
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
