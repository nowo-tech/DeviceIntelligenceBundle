<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Messenger;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class CleanupMessage
{
    public function __construct(public string $olderThan = 'P180D')
    {
    }
}
