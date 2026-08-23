<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\EventSubscriber;

use Nowo\DeviceIntelligenceBundle\Attribute\DeviceRateLimit;
use Nowo\DeviceIntelligenceBundle\Attribute\DeviceRisk;
use Nowo\DeviceIntelligenceBundle\Attribute\RequireTrustedDevice;
use Nowo\DeviceIntelligenceBundle\RateLimiter\DeviceRateLimiterInterface;
use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Enforces #[DeviceRisk], #[DeviceRateLimit], and #[RequireTrustedDevice] (403 on fail).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ControllerAttributeSubscriber implements EventSubscriberInterface
{
    public function __construct(private DeviceRateLimiterInterface $limiter)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onController', 16],
        ];
    }

    public function onController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $context = $request->attributes->get('_device');
        $context = $context instanceof DeviceContext ? $context : null;

        foreach ($this->attributes($event) as $attribute) {
            if ($attribute instanceof DeviceRisk) {
                $score = $context?->risk()->score() ?? 0;
                if ($score > $attribute->max) {
                    throw new AccessDeniedHttpException('Device risk exceeds the allowed maximum.');
                }
            }
            if ($attribute instanceof RequireTrustedDevice && ($context === null || !$context->isTrusted())) {
                throw new AccessDeniedHttpException('A trusted device is required.');
            }
            if ($attribute instanceof DeviceRateLimit) {
                $ipHash   = hash('sha256', (string) $request->getClientIp());
                $userId   = $context?->analysis()->observation()->userIdentifier?->value;
                $deviceId = $context?->device()->id->value;
                $ok       = $this->limiter->consume(
                    'attribute',
                    $attribute->policy,
                    $ipHash,
                    $userId,
                    $deviceId,
                    $attribute->limit,
                    $attribute->interval,
                );
                if (!$ok) {
                    throw new AccessDeniedHttpException('Device rate limit exceeded.');
                }
            }
        }
    }

    /**
     * @return list<object>
     */
    private function attributes(ControllerEvent $event): array
    {
        $out = [];
        foreach ($event->getAttributes() as $list) {
            foreach ($list as $attribute) {
                $out[] = $attribute;
            }
        }

        return $out;
    }
}
