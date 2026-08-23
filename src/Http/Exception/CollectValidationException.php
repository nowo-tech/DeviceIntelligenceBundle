<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Http\Exception;

/**
 * Invalid collect payload or transport checks.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class CollectValidationException extends \RuntimeException
{
    public function __construct(string $message, private int $statusCode = 400)
    {
        parent::__construct($message, $statusCode);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
