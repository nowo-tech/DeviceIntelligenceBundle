<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Privacy\PrivacyContext;
use Nowo\DeviceIntelligence\Privacy\PrivacyMode;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligence\User\UserIdentifier;

/**
 * Framework-agnostic input. The Symfony bundle maps Request → this DTO.
 */
final readonly class AnalysisInput
{
    /**
     * @param array<string, string> $headers lowercase keys
     */
    public function __construct(
        public DateTimeImmutable $now,
        public SignalBag $clientSignals = new SignalBag([]),
        public ?string $clientIp = null,
        public ?string $userAgent = null,
        public array $headers = [],
        public ?string $sessionId = null,
        public ?UserIdentifier $userIdentifier = null,
        public ?string $sdkVersion = null,
        public int $schemaVersion = 1,
        public bool $highEntropyConsent = true,
        public ?string $nonce = null,
        public PrivacyContext $privacy = new PrivacyContext(PrivacyMode::Balanced),
        public string $ipSalt = 'device-intelligence',
    ) {
    }
}
