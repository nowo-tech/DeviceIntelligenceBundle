<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Flashes when the password succeeded on a new device cluster.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DemoLoginSubscriber implements EventSubscriberInterface
{
    public function __construct(private RequestStack $requests)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        unset($event);
        $request = $this->requests->getCurrentRequest();
        $device = $request?->attributes->get('_device');
        if (!$device instanceof DeviceContext) {
            $request?->getSession()->getFlashBag()->add('info', 'demo.flash.login_ok');

            return;
        }
        if ($device->isNew()) {
            $request->getSession()->getFlashBag()->add('warning', 'demo.flash.login_new_device');

            return;
        }

        $request->getSession()->getFlashBag()->add('success', 'demo.flash.login_known_device');
    }
}
