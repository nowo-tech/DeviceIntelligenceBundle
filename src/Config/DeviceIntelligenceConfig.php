<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Config;

/**
 * Processed bundle configuration accessor. Request-safe: immutable after boot.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DeviceIntelligenceConfig
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private array $config)
    {
    }

    /**
     * Whether device intelligence is enabled.
     */
    public function enabled(): bool
    {
        return (bool) $this->config['enabled'];
    }

    /**
     * Name of the default profile.
     */
    public function defaultProfileName(): string
    {
        return (string) $this->config['default_profile'];
    }

    /**
     * @return array<string, mixed>
     */
    public function profile(?string $name = null): array
    {
        $key = $name ?? $this->defaultProfileName();

        return $this->config['profiles'][$key] ?? $this->config['profiles'][$this->defaultProfileName()];
    }

    /**
     * @return array<string, mixed>
     */
    public function endpoint(): array
    {
        return $this->config['endpoint'];
    }

    /**
     * @return array<string, mixed>
     */
    public function tokenCookie(): array
    {
        return $this->config['token_cookie'];
    }

    /**
     * Lifetime of the device token in seconds.
     */
    public function tokenTtl(): int
    {
        return (int) $this->config['token_ttl'];
    }

    /**
     * Whether every request is observed (not only the collect endpoint).
     */
    public function observeOnEveryRequest(): bool
    {
        return (bool) $this->config['observe_on_every_request'];
    }

    /**
     * Salt used to hash client IP addresses.
     */
    public function ipSalt(): string
    {
        return (string) $this->config['ip_salt'];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->config;
    }
}
