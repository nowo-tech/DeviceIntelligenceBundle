<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\EventSubscriber;

use Nowo\DeviceIntelligence\Device\DeviceManager;
use Nowo\DeviceIntelligence\User\UserIdentifierResolverInterface;
use Nowo\DeviceIntelligence\Velocity\VelocityEngineInterface;
use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Throwable;

use function is_object;

/**
 * Associates users on login, records velocity, never auto-trusts. Logout keeps the cookie.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SecurityDeviceSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private DeviceManager $devices,
        private UserIdentifierResolverInterface $users,
        private VelocityEngineInterface $velocity,
        private RequestStack $requests,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        $events = [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
            LogoutEvent::class       => 'onLogout',
        ];
        if (class_exists(InteractiveLoginEvent::class)) {
            $events[InteractiveLoginEvent::class] = 'onInteractiveLogin';
        }

        return $events;
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $this->associate($event->getUser());
    }

    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        if (is_object($user)) {
            $this->associate($user);
        }
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        unset($event);
        $context = $this->context();
        if ($context === null) {
            return;
        }
        $this->velocity->increment('login_failure', $context->device());
        $this->logger?->info('device_intelligence.login_failure', [
            'device_id' => $context->device()->id->value,
        ]);
    }

    public function onLogout(LogoutEvent $event): void
    {
        unset($event);
        // Intentionally keep the observation cookie so the next session can match the device.
    }

    private function associate(object $user): void
    {
        $context = $this->context();
        if ($context === null) {
            return;
        }
        try {
            $identifier = $this->users->resolve($user);
        } catch (Throwable $e) {
            $this->logger?->info('device_intelligence.user_resolve_failed', ['error' => $e->getMessage()]);

            return;
        }
        $this->devices->associate($context->device(), $identifier);
        $this->velocity->increment('login', $context->device());
        $this->logger?->info('device_intelligence.login_associated', [
            'device_id' => $context->device()->id->value,
        ]);
    }

    private function context(): ?DeviceContext
    {
        $request = $this->requests->getCurrentRequest();
        $device  = $request?->attributes->get('_device');

        return $device instanceof DeviceContext ? $device : null;
    }
}
