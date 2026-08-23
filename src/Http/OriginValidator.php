<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Http;

use Nowo\DeviceIntelligenceBundle\Config\DeviceIntelligenceConfig;
use Nowo\DeviceIntelligenceBundle\Http\Exception\CollectValidationException;
use Symfony\Component\HttpFoundation\Request;

use function in_array;
use function is_array;
use function is_string;

use const PHP_URL_HOST;

/**
 * CSRF checks for the collect endpoint: Origin, double-submit cookie, or none.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class OriginValidator
{
    public function __construct(private DeviceIntelligenceConfig $config)
    {
    }

    public function validate(Request $request): void
    {
        $mode = (string) $this->config->endpoint()['csrf'];
        if ($mode === 'none') {
            return;
        }
        if ($mode === 'double_submit') {
            $this->validateDoubleSubmit($request);

            return;
        }

        $this->validateOrigin($request);
    }

    private function validateOrigin(Request $request): void
    {
        $origin = $request->headers->get('Origin') ?? $request->headers->get('Referer');
        if ($origin === null || $origin === '') {
            throw new CollectValidationException('Missing Origin or Referer header.');
        }
        $host = parse_url($origin, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            throw new CollectValidationException('Invalid Origin header.');
        }

        $allowed      = $this->config->endpoint()['allowed_origins'] ?? [];
        $allowed      = is_array($allowed) ? $allowed : [];
        $allowedHosts = [$request->getHost()];
        foreach ($allowed as $entry) {
            if (!is_string($entry) || $entry === '') {
                continue;
            }
            $parsed         = parse_url($entry, PHP_URL_HOST);
            $allowedHosts[] = is_string($parsed) && $parsed !== '' ? $parsed : $entry;
        }

        if (!in_array($host, $allowedHosts, true)) {
            throw new CollectValidationException('Origin is not allowed.', 403);
        }
    }

    private function validateDoubleSubmit(Request $request): void
    {
        $header = (string) $request->headers->get('X-CSRF-Token', '');
        $cookie = (string) $request->cookies->get('di_csrf', '');
        if ($header === '' || $cookie === '' || !hash_equals($cookie, $header)) {
            throw new CollectValidationException('CSRF token mismatch.', 403);
        }
    }
}
