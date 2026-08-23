<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\EventSubscriber;

use Nowo\DeviceIntelligence\Device\DeviceManager;
use Nowo\DeviceIntelligence\Observation\ObservationId;
use Nowo\DeviceIntelligence\Port\ObservationRepositoryInterface;
use Nowo\DeviceIntelligence\User\UserIdentifierResolverInterface;
use Nowo\DeviceIntelligenceBundle\Config\DeviceIntelligenceConfig;
use Nowo\DeviceIntelligenceBundle\Http\ObservationTokenIssuer;
use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;
use Nowo\DeviceIntelligenceBundle\Request\TokenDeviceContextFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Sets `_device` from the observation cookie after collect (and whenever
 * observe_on_every_request is true). Does not rematch.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DeviceRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private DeviceIntelligenceConfig $config,
        private ObservationTokenIssuer $tokens,
        private ObservationRepositoryInterface $observations,
        private DeviceManager $devices,
        private TokenDeviceContextFactory $contexts,
        private ?UserIdentifierResolverInterface $users = null,
        private ?TokenStorageInterface $security = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 8],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->config->enabled()) {
            return;
        }
        $request = $event->getRequest();
        if ($request->attributes->get('_device') instanceof DeviceContext) {
            return;
        }

        $cookieName = (string) $this->config->tokenCookie()['name'];
        if (!$this->config->observeOnEveryRequest() && !$request->cookies->has($cookieName)) {
            return;
        }

        $token = $this->tokens->read($request);
        if (null === $token) {
            return;
        }

        try {
            $observation = $this->observations->find(new ObservationId($token['observation_id']));
        } catch (\Throwable $e) {
            $this->logger?->info('device_intelligence.token_lookup_failed', ['error' => $e->getMessage()]);

            return;
        }
        if (null === $observation) {
            return;
        }
        $device = $this->devices->get($observation->deviceId);
        if (null === $device) {
            return;
        }

        $trusted = false;
        $userObj = $this->security?->getToken()?->getUser();
        if (\is_object($userObj) && null !== $this->users) {
            try {
                $trusted = $this->devices->isTrusted($device, $this->users->resolve($userObj));
            } catch (\Throwable) {
                $trusted = false;
            }
        }

        $request->attributes->set('_device', $this->contexts->fromStored($device, $observation, $trusted));
    }
}
