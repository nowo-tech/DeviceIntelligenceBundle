<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Clears bundle-owned per-request state at the start of every main request, so long-running workers
 * behave like PHP-FPM even when `services_resetter` does not run between requests.
 *
 * Services are collected with the {@see self::TAG} tag and must expose a public `reset(): void` method.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class RequestStateResetSubscriber implements EventSubscriberInterface
{
    public const TAG = 'nowo_device_intelligence.request_reset';

    /**
     * @param iterable<object> $services
     */
    public function __construct(private iterable $services = [])
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 4096],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        foreach ($this->services as $service) {
            if (method_exists($service, 'reset')) {
                $service->reset();
            }
        }
    }
}
