<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Event;

use DateTimeImmutable;

/**
 * Dispatched after the commit of a personal-data erasure that ran in its own transaction (1.9, GDPR ports). Inside the
 * caller's transaction (e.g. UserKit's eraser) no event is dispatched: the returned result is the outcome.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class DeviceIntelligencePersonalDataErasedEvent
{
    /**
     * @param string                                                                        $userIdentifier the erased subject
     * @param array{observations: int, device_users: int, device_trusts: int, devices: int} $counts         rows deleted per kind
     */
    public function __construct(
        public string $userIdentifier,
        public array $counts,
        public DateTimeImmutable $erasedAt,
    ) {
    }
}
