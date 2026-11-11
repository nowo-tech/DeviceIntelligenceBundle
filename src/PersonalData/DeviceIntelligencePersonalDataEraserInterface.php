<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\PersonalData;

use Nowo\DeviceIntelligenceBundle\Event\DeviceIntelligencePersonalDataErasedEvent;

/**
 * Erases one person's device data (1.9, GDPR art. 17): the person's observations, device ↔ user links and trust
 * grants, then the devices nothing else references any more. Devices still linked to or observed for another user, or
 * observed anonymously, are kept.
 *
 * Idempotent (a second call returns zero counts). Rows are matched byte for byte on the stored user identifier.
 * When no transaction is open on the bundle's connection, the call runs in its own transaction and dispatches
 * {@see DeviceIntelligencePersonalDataErasedEvent} after the commit; inside the
 * caller's transaction it joins it and dispatches no event (the returned result is the outcome).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface DeviceIntelligencePersonalDataEraserInterface
{
    /**
     * Deletes the subject's rows and returns how many rows of each kind were deleted.
     */
    public function erase(DeviceIntelligencePersonalDataSubject $subject): DeviceIntelligenceErasureResult;
}
