# Use cases

Device Intelligence answers: *is this browser cluster familiar, and how risky is this request?* Use it to **step-up MFA**, **rate-limit**, and **prefer trusted devices**. Never use a Device ID as a password, session, or MFA factor — client signals are attacker-controlled.

Copy-paste controller, attribute, event, and browser examples below. APIs: [USAGE.md](USAGE.md). Config: [CONFIGURATION.md](CONFIGURATION.md). Security contract: [SECURITY.md](SECURITY.md).

Live collect + multi-example demo: Symfony 8 (`demo/symfony8`, http://localhost:8038). Each example has its own path (`/en`, `/en/checkout`, `/en/login`, `/en/trust`, `/en/privileged`, `/en/coupon`, `/en/export`, `/en/alerts`). Users `alice` / `vip`, password `password`.

## Table of contents

- [How to pick a pattern](#how-to-pick-a-pattern)
- [Shared wiring (collect + cookie)](#shared-wiring-collect--cookie)
- [1. Checkout / payment step-up](#1-checkout--payment-step-up)
- [2. Login from a new device](#2-login-from-a-new-device)
- [3. Remember this device (explicit trust)](#3-remember-this-device-explicit-trust)
- [4. Privileged action on a trusted device](#4-privileged-action-on-a-trusted-device)
- [5. Promo / coupon abuse](#5-promo--coupon-abuse)
- [6. Hard deny on extreme risk](#6-hard-deny-on-extreme-risk)
- [7. Custom risk rule](#7-custom-risk-rule)
- [8. Alert on suspicious devices](#8-alert-on-suspicious-devices)
- [What not to do](#what-not-to-do)

## How to pick a pattern

| Situation | Pattern | Why |
| --------- | ------- | --- |
| Pay, change IBAN, export data | Manual `DeviceContext` + MFA when `risk()->score() >= 70` | Host owns the MFA challenge; the bundle only scores |
| First login on a laptop | `isNew()` / `SuspiciousDeviceEvent` | Warn or email; do not block the password check |
| “Remember this browser” | `DeviceTrustService::trust()` after MFA | Login never auto-trusts |
| Payroll, API keys, disable 2FA | `#[RequireTrustedDevice]` | 403 unless that user explicitly trusted this device |
| Coupon / trial / vote | `#[DeviceRateLimit]` or named YAML policy | Key by `device` or `device_ip`, not raw IP |
| Obvious automation / critical score | `#[DeviceRisk(max: 89)]` | Hard 403; keep a higher bar than step-up |
| Business-specific score | `#[AsDeviceRiskRule]` | Explainable `RiskResult`; enable under `profiles.*.risk.rules` |

Default YAML decisions (override in config): **observe** ≥ 40, **step_up** ≥ 70, **block** ≥ 90. `#[DeviceRisk(max: 70)]` denies when the score is **greater than** 70.

## Shared wiring (collect + cookie)

All server examples assume collect has run at least once in that browser (HttpOnly cookie `di_obs`). Without a cookie, `DeviceContext` is missing on HTML requests unless you type the argument as nullable.

**1. Config** (enable Doctrine tables, return risk to the client, hydrate later HTML requests):

```yaml
# config/packages/nowo_device_intelligence.yaml
nowo_device_intelligence:
    observe_on_every_request: true
    endpoint:
        csrf: origin
        response:
            device_id: true
            confidence: true
            risk: true
    doctrine:
        enabled: true
        table_prefix: device_intelligence_
```

**2. Browser** — IIFE after `php bin/console assets:install`:

```twig
<script src="{{ asset('js/device-intelligence.min.js', 'nowo_device_intelligence') }}"></script>
<script>
  const device = new DeviceIntelligence({ endpoint: '/_device/collect', timeout: 8000 });
  device.collect().then((result) => {
    if (!result.ok) {
      return;
    }
    // result.deviceId, result.confidence, result.risk, result.new, result.degraded
  });
</script>
```

Pentatrion Vite (recommended in Symfony apps):

```ts
import { DeviceIntelligence } from '@bundle/src/index.ts';

const device = new DeviceIntelligence({ endpoint: '/_device/collect' });
const result = await device.collect();
```

`collect()` never throws. Failures resolve to `{ ok: false, degraded: true }`.

**3. Controller argument** — optional on public pages, required only after you know collect ran:

```php
use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;
use Symfony\Component\HttpFoundation\Response;

public function page(?DeviceContext $device = null): Response
{
    if (null === $device) {
        // First HTML hit, cookie missing, or collect still in flight.
        return $this->render('page.html.twig');
    }

    $device->device()->id->value;
    $device->isNew();
    $device->isTrusted();
    $device->match()->confidence();
    $device->risk()->score();       // 0..100
    $device->risk()->level()->value; // low|medium|high|critical
    $device->risk()->reasons();     // list of reason labels

    return $this->render('page.html.twig');
}
```

---

## 1. Checkout / payment step-up

**When:** money movement, beneficiary change, large export. Score the device, then **your** MFA / 3-D Secure. Do not refuse the card solely on Device ID.

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CheckoutController extends AbstractController
{
    #[Route('/checkout/pay', name: 'checkout_pay', methods: ['POST'])]
    public function pay(Request $request, ?DeviceContext $device = null): Response
    {
        $score = $device?->risk()->score() ?? 0;

        if ($score >= 90) {
            throw $this->createAccessDeniedException('Device risk is too high for this payment.');
        }

        if ($score >= 70 || ($device?->isNew() ?? true)) {
            // Redirect to your MFA / 3DS challenge, then resume the payment.
            $request->getSession()->set('checkout.pending', true);

            return $this->redirectToRoute('security_mfa');
        }

        // Charge the customer (authorization + CSRF already applied).
        return $this->redirectToRoute('checkout_done');
    }
}
```

Same idea with a **hard ceiling** on the controller (403 when score > 89) and step-up in the method for the 70–89 band:

```php
use Nowo\DeviceIntelligenceBundle\Attribute\DeviceRisk;

#[DeviceRisk(max: 89)]
#[Route('/checkout/pay', methods: ['POST'])]
public function pay(?DeviceContext $device = null): Response
{
    if (($device?->risk()->score() ?? 0) >= 70) {
        return $this->redirectToRoute('security_mfa');
    }

    return $this->redirectToRoute('checkout_done');
}
```

---

## 2. Login from a new device

**When:** password (or SSO) already succeeded. The bundle **associates** the user with the current device on `LoginSuccessEvent` and increments login velocity. It does **not** mark the device trusted.

```php
<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class NewDeviceLoginSubscriber implements EventSubscriberInterface
{
    public function __construct(private RequestStack $requests)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $this->requests->getCurrentRequest();
        $device = $request?->attributes->get('_device');
        if (!$device instanceof DeviceContext || !$device->isNew()) {
            return;
        }

        $event->getRequest()->getSession()->set('security.new_device', true);
        // Host: send “new browser signed in” email. Do not invalidate the session solely on isNew().
    }
}
```

Failed passwords increment velocity key `login_failure` when `_device` is present. Logout **keeps** `di_obs` so the next session can match the same cluster.

---

## 3. Remember this device (explicit trust)

**When:** the user ticks “remember this browser” **after** MFA (or a similar proof). Trust is a grant with optional TTL and label.

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;
use Nowo\DeviceIntelligenceBundle\Trust\DeviceTrustService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TrustedDeviceController extends AbstractController
{
    public function __construct(private DeviceTrustService $trust)
    {
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/settings/devices/trust', name: 'device_trust', methods: ['POST'])]
    public function trust(?DeviceContext $device = null): Response
    {
        $user = $this->getUser();
        if (null === $device || null === $user) {
            throw $this->createAccessDeniedException('No device observation on this request.');
        }

        $this->trust->trust(
            $device->device(),
            new UserIdentifier($user->getUserIdentifier()),
            new \DateTimeImmutable('+90 days'),
            'Browser · '.$user->getUserIdentifier(),
        );

        return $this->redirectToRoute('settings_devices');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/settings/devices/revoke', name: 'device_revoke', methods: ['POST'])]
    public function revoke(?DeviceContext $device = null): Response
    {
        $user = $this->getUser();
        if (null === $device || null === $user) {
            throw $this->createAccessDeniedException('No device observation on this request.');
        }

        $this->trust->revoke(
            $device->device(),
            new UserIdentifier($user->getUserIdentifier()),
        );

        return $this->redirectToRoute('settings_devices');
    }
}
```

List devices for the account:

```bash
php bin/console device-intelligence:user:devices alice@example.test
php bin/console device-intelligence:device:show 01ARZ3NDEKTSV4RRFFQ69G5FAV
```

---

## 4. Privileged action on a trusted device

**When:** disable 2FA, create API tokens, payroll. Require an **explicit** trust grant, not merely a returning Device ID.

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\DeviceIntelligenceBundle\Attribute\DeviceRisk;
use Nowo\DeviceIntelligenceBundle\Attribute\RequireTrustedDevice;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class SecuritySettingsController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[RequireTrustedDevice]
    #[DeviceRisk(max: 70)]
    #[Route('/settings/api-tokens', name: 'settings_api_tokens', methods: ['POST'])]
    public function createToken(): Response
    {
        // 403 if the device is not trusted for this user, or risk score > 70.
        return new Response('created');
    }
}
```

`#[RequireTrustedDevice]` reads `_device` and `$context->isTrusted()`. On the first HTML request there is no cookie → 403. Collect first (or reload after collect) so hydration can run.

---

## 5. Promo / coupon abuse

**When:** one human / browser cluster should not redeem unbounded trials. Prefer a **device** key; missing device falls back to hashed IP.

**Attribute** (limit and interval on the action; `policy` is the compound key):

```php
use Nowo\DeviceIntelligenceBundle\Attribute\DeviceRateLimit;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[DeviceRateLimit(limit: 3, interval: '24 hours', policy: 'device')]
#[Route('/promo/redeem', methods: ['POST'])]
public function redeem(): Response
{
    return new Response('ok');
}
```

`policy` values: `ip`, `user`, `device`, `device_ip`, `user_device`. Exceeding the window is **403**.

**Named YAML policy** + service (shared limits across several controllers):

```yaml
nowo_device_intelligence:
    profiles:
        default:
            rate_limit:
                policies:
                    coupon:
                        limit: 3
                        interval: '24 hours'
```

```php
use Nowo\DeviceIntelligenceBundle\RateLimiter\DeviceRateLimiterInterface;
use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

public function redeem(
    Request $request,
    DeviceRateLimiterInterface $limiter,
    ?DeviceContext $device = null,
): Response {
    $ok = $limiter->consume(
        'coupon',
        'device',
        hash('sha256', (string) $request->getClientIp()),
        $this->getUser()?->getUserIdentifier(),
        $device?->device()->id->value,
    );
    if (!$ok) {
        throw $this->createAccessDeniedException('Coupon rate limit exceeded.');
    }

    return new Response('ok');
}
```

---

## 6. Hard deny on extreme risk

**When:** you already have MFA elsewhere and only want a safety net for `critical` scores (automation, mutation, velocity).

```php
use Nowo\DeviceIntelligenceBundle\Attribute\DeviceRisk;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[DeviceRisk(max: 89)]
#[Route('/account/export', methods: ['POST'])]
public function export(): Response
{
    return new Response('ok');
}
```

Missing `_device` is treated as score `0` for `#[DeviceRisk]` (the guard does not fail closed). Combine with collect-on-every-app-shell and `observe_on_every_request: true` for HTML after the cookie exists. For money movement prefer [checkout step-up](#1-checkout--payment-step-up) rather than relying on this default.

---

## 7. Custom risk rule

**When:** built-in rules are not enough (VIP allowlist, product-specific velocity). Implement `RiskRuleInterface`, tag with `#[AsDeviceRiskRule]`, enable in YAML.

```php
<?php

declare(strict_types=1);

namespace App\Risk;

use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Risk\RiskResult;
use Nowo\DeviceIntelligence\Risk\RiskRuleInterface;
use Nowo\DeviceIntelligenceBundle\Attribute\AsDeviceRiskRule;

#[AsDeviceRiskRule(priority: 10)]
final class VipAllowlistRule implements RiskRuleInterface
{
    public function name(): string
    {
        return 'vip_allowlist';
    }

    public function evaluate(RiskContext $context): RiskResult
    {
        $user = $context->observation->userIdentifier?->value;
        if ('vip:alice@example.test' === $user) {
            return new RiskResult(-25, $this->name(), ['vip' => true]);
        }

        return new RiskResult(0, $this->name());
    }
}
```

```yaml
nowo_device_intelligence:
    profiles:
        default:
            risk:
                rules:
                    vip_allowlist: { enabled: true }
                    new_device: { enabled: true }
                    trusted_device: { enabled: true }
```

Negative contributions lower the score (same idea as built-in `trusted_device`). Reason ids stay technical in the profiler. More: [custom-rules.md](custom-rules.md), [risk-engine.md](risk-engine.md).

---

## 8. Alert on suspicious devices

**When:** you want Slack / email / a SIEM event whenever the assessment is **high** or **critical**, without blocking the request in this listener.

```php
<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Nowo\DeviceIntelligenceBundle\Event\SuspiciousDeviceEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class SuspiciousDeviceAlertSubscriber implements EventSubscriberInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [SuspiciousDeviceEvent::class => 'onSuspicious'];
    }

    public function onSuspicious(SuspiciousDeviceEvent $event): void
    {
        $analysis = $event->analysis;
        $this->logger->warning('suspicious_device', [
            'device_id' => $analysis->device()->id->value,
            'score' => $analysis->riskScore(),
            'level' => $analysis->riskLevel(),
            'reasons' => $analysis->riskReasons(),
        ]);
    }
}
```

Other useful events (matcher is not mutated): `DeviceObservedEvent`, `NewDeviceDetectedEvent`, `DeviceMatchedEvent`, `DeviceTrustedEvent`, `DeviceRevokedEvent`. See [USAGE.md](USAGE.md#events).

---

## What not to do

- **Do not** log the user in because `deviceId` matched. Pair with a real authenticator.
- **Do not** treat `di_obs` as a session cookie. It is an HMAC observation pointer.
- **Do not** dump raw canvas / audio / font lists. The profiler already truncates summaries.
- **Do not** set `endpoint.csrf: none` on a public origin. Keep `origin` or `double_submit`.
- **Do not** share in-memory repositories across FrankenPHP worker requests (`doctrine.enabled: true` in production).
