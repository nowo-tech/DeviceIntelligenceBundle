<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Nowo\DeviceIntelligenceBundle\Event\SuspiciousDeviceEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Stores the last high/critical assessment for the Alerts demo case.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DemoSuspiciousDeviceSubscriber implements EventSubscriberInterface
{
    public function __construct(private RequestStack $requests)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [SuspiciousDeviceEvent::class => 'onSuspicious'];
    }

    public function onSuspicious(SuspiciousDeviceEvent $event): void
    {
        $request = $this->requests->getCurrentRequest();
        if (null === $request || !$request->hasSession()) {
            return;
        }

        $analysis = $event->analysis;
        $request->getSession()->set('demo.suspicious', [
            'device_id' => $analysis->device()->id->value,
            'score' => $analysis->riskScore(),
            'level' => $analysis->riskLevel(),
            'reasons' => $analysis->riskReasons(),
            'at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }
}
