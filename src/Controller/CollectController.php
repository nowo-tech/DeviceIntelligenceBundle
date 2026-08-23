<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Controller;

use Nowo\DeviceIntelligenceBundle\Config\DeviceIntelligenceConfig;
use Nowo\DeviceIntelligenceBundle\Event\AnalyzeService;
use Nowo\DeviceIntelligenceBundle\Http\AnalysisInputFactory;
use Nowo\DeviceIntelligenceBundle\Http\CollectRequestValidator;
use Nowo\DeviceIntelligenceBundle\Http\Exception\CollectValidationException;
use Nowo\DeviceIntelligenceBundle\Http\ObservationTokenIssuer;
use Nowo\DeviceIntelligenceBundle\RateLimiter\DeviceRateLimiterInterface;
use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

use function is_string;

/**
 * POST collect endpoint. Route name: nowo_device_intelligence_collect.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsController]
final class CollectController
{
    public function __construct(
        private CollectRequestValidator $validator,
        private AnalysisInputFactory $inputs,
        private AnalyzeService $analyze,
        private ObservationTokenIssuer $tokens,
        private DeviceIntelligenceConfig $config,
        private DeviceRateLimiterInterface $limiter,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Ingest compact client signals and return a signed observation token.
     */
    #[Route(
        path: '/_device/collect',
        name: 'nowo_device_intelligence_collect',
        methods: ['POST'],
    )]
    public function __invoke(Request $request): Response
    {
        if (!$this->config->enabled() || !$this->config->endpoint()['enabled']) {
            return new JsonResponse(['error' => 'disabled'], Response::HTTP_NOT_FOUND);
        }

        $ipHash = hash('sha256', (string) $request->getClientIp());
        if (!$this->limiter->consume('collect', 'ip', $ipHash, null, null)) {
            return new JsonResponse(['error' => 'rate_limited'], Response::HTTP_FORBIDDEN);
        }

        try {
            $payload = $this->validator->validate($request);
        } catch (CollectValidationException $e) {
            $this->logger->info('device_intelligence.collect_rejected', [
                'channel' => 'device_intelligence',
                'reason'  => $e->getMessage(),
            ]);

            return new JsonResponse(['error' => $e->getMessage()], $e->statusCode());
        }

        $input    = $this->inputs->fromRequest($request, $payload);
        $analysis = $this->analyze->analyze($input);
        $context  = new DeviceContext($analysis, false);
        $request->attributes->set('_device', $context);

        $nonce  = is_string($payload['nonce'] ?? null) ? $payload['nonce'] : '';
        $cookie = $this->tokens->issue($analysis->observation()->id, $nonce, $request->isSecure());

        $body = [
            'ok'       => true,
            'new'      => $analysis->match()->isNewDevice(),
            'degraded' => $analysis->degraded(),
        ];
        $flags = $this->config->endpoint()['response'];
        if ($flags['token']) {
            $body['token']     = $cookie->getValue();
            $body['expiresAt'] = $cookie->getExpiresTime();
        }
        if ($flags['device_id']) {
            $body['deviceId'] = $analysis->device()->id->value;
        }
        if ($flags['confidence']) {
            $body['confidence'] = $analysis->matchConfidence();
        }
        if ($flags['risk']) {
            $body['risk'] = [
                'score' => $analysis->riskScore(),
                'level' => $analysis->riskLevel(),
            ];
        }

        $this->logger->info('device_intelligence.collect', [
            'channel'             => 'device_intelligence',
            'device_id_hash'      => substr(hash('sha256', $analysis->device()->id->value), 0, 16),
            'observation_id_hash' => substr(hash('sha256', $analysis->observation()->id->value), 0, 16),
            'risk'                => $analysis->riskScore(),
            'new'                 => $analysis->match()->isNewDevice(),
        ]);

        $response = new JsonResponse($body);
        $response->headers->setCookie($cookie);

        return $response;
    }
}
