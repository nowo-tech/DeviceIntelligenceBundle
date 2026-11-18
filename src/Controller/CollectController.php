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
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\ConflictCode;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\TransientFailureClassifier;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * POST collect endpoint. Route name: nowo_device_intelligence_collect.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class CollectController
{
    private ConflictResponsePolicy $conflictPolicy;

    /**
     * Creates the collect controller.
     */
    public function __construct(
        private CollectRequestValidator $validator,
        private AnalysisInputFactory $inputs,
        private AnalyzeService $analyze,
        private ObservationTokenIssuer $tokens,
        private DeviceIntelligenceConfig $config,
        private DeviceRateLimiterInterface $limiter,
        private LoggerInterface $logger,
        ?ConflictResponsePolicy $conflictPolicy = null,
    ) {
        $this->conflictPolicy = $conflictPolicy ?? new ConflictResponsePolicy();
    }

    /**
     * Ingest compact client signals and return a signed observation token.
     */
    #[Route(
        path: '/_device/collect',
        name: 'nowo_device_intelligence_collect',
        methods: ['POST'],
    )]
    /**
     * Validates the collect payload, records the observation and answers with the device context.
     */
    public function __invoke(Request $request): Response
    {
        if (!$this->config->enabled() || !$this->config->endpoint()['enabled']) {
            return new JsonResponse(['error' => 'disabled'], Response::HTTP_NOT_FOUND);
        }

        $ipHash = hash('sha256', (string) $request->getClientIp());
        if (!$this->limiter->consume('collect', 'ip', $ipHash, null, null)) {
            return new JsonResponse(['error' => 'rate_limited'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        try {
            $payload = $this->validator->validate($request);
        } catch (CollectValidationException $e) {
            $this->logger->info('device_intelligence.collect_rejected', [
                'channel' => 'device_intelligence',
                'reason' => $e->getMessage(),
            ]);

            return new JsonResponse(['error' => $e->getMessage()], $e->statusCode());
        }

        $input = $this->inputs->fromRequest($request, $payload);
        try {
            $analysis = $this->analyze->analyze($input);
        } catch (Throwable $failure) {
            if (!self::isLockConflict($failure)) {
                throw $failure;
            }

            return $this->conflict($request, $failure);
        }
        $context = new DeviceContext($analysis, false);
        $request->attributes->set('_device', $context);

        $nonce = \is_string($payload['nonce'] ?? null) ? $payload['nonce'] : '';
        $cookie = $this->tokens->issue($analysis->observation()->id, $nonce, $request->isSecure());

        $body = [
            'ok' => true,
            'new' => $analysis->match()->isNewDevice(),
            'degraded' => $analysis->degraded(),
        ];
        $flags = $this->config->endpoint()['response'];
        if ($flags['token']) {
            $body['token'] = $cookie->getValue();
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
            'channel' => 'device_intelligence',
            'device_id_hash' => substr(hash('sha256', $analysis->device()->id->value), 0, 16),
            'observation_id_hash' => substr(hash('sha256', $analysis->observation()->id->value), 0, 16),
            'risk' => $analysis->riskScore(),
            'new' => $analysis->match()->isNewDevice(),
        ]);

        $response = new JsonResponse($body);
        $response->headers->setCookie($cookie);

        return $response;
    }

    /**
     * 1.10 (REQ-RUNTIME-003 rule 6): a lock conflict of the analysis write unit. `{"error": "conflict", "code": ...}`
     * with the policy's status (409 by default, 503 when configured) and `X-Conflict-Code`:
     * - CONFLICT_001 + `Retry-After` when nothing of the request was committed (the unit rolled back as a whole);
     * - CONFLICT_002, no `Retry-After`, when something was committed earlier in the request (transaction-doctrine's
     *   request watermark) or the commit outcome is unknown (a lost savepoint).
     */
    private function conflict(Request $request, Throwable $failure): JsonResponse
    {
        $code = ConflictCode::forRequest($request, $failure);
        $hint = null;
        for ($current = $failure; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof TransientStorageFailure) {
                $hint = $current->retryAfterSeconds();

                break;
            }
        }
        $this->logger->notice('device_intelligence.collect_conflict', [
            'channel' => 'device_intelligence',
            'code' => $code->value,
            'exception' => $failure::class,
        ]);

        return new JsonResponse(['error' => 'conflict', 'code' => $code->value], $this->conflictPolicy->status, $this->conflictPolicy->headersFor($code, null !== $hint && $hint > 0 ? $hint : null));
    }

    /**
     * A deadlock, lock wait timeout or serialization failure (MySQL 1213 / 1205, MariaDB 1020, PostgreSQL 40001 /
     * 40P01 / 55P03), anywhere in the chain or wrapped in the contract's TransientStorageFailure, or a lost savepoint.
     * Unique violations and lost connections are not conflicts.
     */
    private static function isLockConflict(Throwable $failure): bool
    {
        for ($current = $failure; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof TransientStorageFailure) {
                return true;
            }
        }
        $classifier = new TransientFailureClassifier();

        return $classifier->isTransient($failure) || $classifier->isCommitOutcomeUnknown($failure);
    }
}
