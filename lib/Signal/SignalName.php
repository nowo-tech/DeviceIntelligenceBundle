<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Signal;

/**
 * Closed set of known signal names. Unknown client keys are ignored.
 */
enum SignalName: string
{
    case Audio = 'audio';
    case Canvas = 'canvas';
    case Webgl = 'webgl';
    case Gpu = 'gpu';
    case Screen = 'screen';
    case Timezone = 'timezone';
    case Language = 'language';
    case Platform = 'platform';
    case HardwareConcurrency = 'hardware_concurrency';
    case DeviceMemory = 'device_memory';
    case TouchSupport = 'touch_support';
    case ClientHints = 'client_hints';
    case BrowserCapabilities = 'browser_capabilities';
    case AutomationIndicators = 'automation_indicators';
    case Fonts = 'fonts';
    case AcceptHeaders = 'accept_headers';
    case IpAsn = 'ip_asn';
    case Country = 'country';
    case Session = 'session';
    case RequestTiming = 'request_timing';
    case UserAgent = 'user_agent';

    public function entropyCategory(): EntropyCategory
    {
        return match ($this) {
            self::Audio, self::Canvas, self::Webgl, self::Fonts => EntropyCategory::High,
            self::Gpu, self::Screen, self::ClientHints, self::BrowserCapabilities,
            self::HardwareConcurrency, self::DeviceMemory => EntropyCategory::Medium,
            default => EntropyCategory::Low,
        };
    }

    public function expectedStability(): float
    {
        return match ($this) {
            self::Webgl, self::Gpu, self::Platform, self::HardwareConcurrency => 0.95,
            self::Canvas, self::Audio, self::Timezone, self::TouchSupport => 0.85,
            self::Screen, self::ClientHints, self::BrowserCapabilities, self::DeviceMemory => 0.7,
            self::Language, self::UserAgent => 0.55,
            default => 0.4,
        };
    }

    public function isIdentityFeature(): bool
    {
        return match ($this) {
            self::AutomationIndicators, self::Country, self::IpAsn,
            self::Language, self::Session, self::RequestTiming => false,
            default => true,
        };
    }

    public function isHighEntropy(): bool
    {
        return EntropyCategory::High === $this->entropyCategory();
    }
}
