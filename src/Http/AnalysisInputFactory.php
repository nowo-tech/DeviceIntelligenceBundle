<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Http;

use Nowo\DeviceIntelligence\AnalysisInput;
use Nowo\DeviceIntelligence\Privacy\PrivacyContext;
use Nowo\DeviceIntelligence\Signal\SignalFactory;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligence\User\UserIdentifierResolverInterface;
use Nowo\DeviceIntelligenceBundle\Config\DeviceIntelligenceConfig;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Maps an HTTP Request + collect payload to the core AnalysisInput DTO.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class AnalysisInputFactory
{
    public function __construct(
        private DeviceIntelligenceConfig $config,
        private ClockInterface $clock,
        private PrivacyContext $privacy,
        private string $kernelSecret = '',
        private ?UserIdentifierResolverInterface $users = null,
        private ?TokenStorageInterface $tokens = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function fromRequest(Request $request, array $payload): AnalysisInput
    {
        $now = $this->clock->now();

        $signals = [];
        if (isset($payload['signals']) && \is_array($payload['signals'])) {
            $signals = $payload['signals'];
        }

        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[strtolower((string) $name)] = implode(', ', $values);
        }

        $user = $this->resolveUser();
        $sessionId = null;
        if ($request->hasSession()) {
            $sessionId = $request->getSession()->getId();
        }

        $consent = $this->privacy->highEntropyConsent;
        if (isset($payload['consent']) && \is_array($payload['consent'])) {
            $consent = (bool) ($payload['consent']['highEntropy'] ?? $consent);
        } elseif (isset($payload['highEntropyConsent'])) {
            $consent = (bool) $payload['highEntropyConsent'];
        }
        $salt = $this->config->ipSalt();
        if ('' === $salt) {
            $salt = '' !== $this->kernelSecret ? $this->kernelSecret : 'device-intelligence';
        }

        $privacy = new PrivacyContext(
            $this->privacy->mode,
            (bool) $consent,
            $this->privacy->hashIp,
            $this->privacy->storeRawIp,
            $this->privacy->storeUserAgent,
        );

        // Client IP is passed for hashing inside the core. It is not stored unless privacy.store_raw_ip is true.

        return new AnalysisInput(
            $now,
            SignalFactory::bagFromClient($signals, $now),
            $request->getClientIp(),
            $request->headers->get('User-Agent'),
            $headers,
            $sessionId,
            $user,
            isset($payload['sdkVersion']) && \is_string($payload['sdkVersion']) ? $payload['sdkVersion'] : null,
            isset($payload['v']) ? (int) $payload['v'] : (isset($payload['schemaVersion']) ? (int) $payload['schemaVersion'] : 1),
            (bool) $consent,
            isset($payload['nonce']) && \is_string($payload['nonce']) ? $payload['nonce'] : null,
            $privacy,
            $salt,
        );
    }

    private function resolveUser(): ?UserIdentifier
    {
        $token = $this->tokens?->getToken();
        $user = $token?->getUser();
        if (!\is_object($user) || null === $this->users) {
            return null;
        }

        try {
            return $this->users->resolve($user);
        } catch (\Throwable) {
            return null;
        }
    }
}
