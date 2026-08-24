<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\EventSubscriber;

use Nowo\DeviceIntelligenceBundle\Profiler\DeviceIntelligenceDataCollector;
use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\HttpKernel\Profiler\ProfilerStorageInterface;

/**
 * Copies collect() analysis onto the HTML request profiler profile when the
 * browser sent X-Previous-Debug-Token. Writes through profiler storage so
 * unserialized LateDataCollector services are not late-collected again.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ProfilerAjaxBridgeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ?Profiler $profiler = null,
        private ?ProfilerStorageInterface $storage = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onResponse', -2048],
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || null === $this->profiler || null === $this->storage) {
            return;
        }
        $request = $event->getRequest();
        if ('nowo_device_intelligence_collect' !== $request->attributes->get('_route')) {
            return;
        }
        $previous = $request->headers->get('X-Previous-Debug-Token');
        if (!\is_string($previous) || 1 !== preg_match('/^[A-Za-z0-9]{4,16}$/', $previous)) {
            return;
        }
        $context = $request->attributes->get('_device');
        if (!$context instanceof DeviceContext) {
            return;
        }

        try {
            $parent = $this->profiler->loadProfile($previous);
            if (null === $parent || !$parent->hasCollector('nowo_device_intelligence')) {
                return;
            }
            $collector = $parent->getCollector('nowo_device_intelligence');
            if (!$collector instanceof DeviceIntelligenceDataCollector || $collector->hasContext()) {
                return;
            }

            $collector->collectAnalysis($context->analysis(), $context->isTrusted(), 'ajax');
            $this->storage->write($parent);
        } catch (\Throwable $e) {
            $this->logger?->info('device_intelligence.profiler_ajax_bridge_failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
