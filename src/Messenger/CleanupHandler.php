<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Messenger;

use Nowo\DeviceIntelligence\Port\ObservationRepositoryInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Deletes observations older than the given ISO-8601 interval.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsMessageHandler]
final class CleanupHandler
{
    public function __construct(
        private ObservationRepositoryInterface $observations,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CleanupMessage $message): int
    {
        $cutoff = $this->clock->now()->sub(new \DateInterval($message->olderThan));

        return $this->observations->deleteOlderThan($cutoff);
    }
}
