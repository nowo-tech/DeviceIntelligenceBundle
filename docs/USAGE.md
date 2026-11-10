# Usage

Copy-paste scenarios (checkout step-up, new-device login, trusted devices, coupons, custom rules): **[USE-CASES.md](USE-CASES.md)**.

## Table of contents

- [Collect endpoint](#collect-endpoint)
- [Controller argument](#controller-argument)
- [Use cases](#use-cases)
- [Events](#events)
- [Security hooks](#security-hooks)
- [Using it from a DDD / hexagonal application](#using-it-from-a-ddd--hexagonal-application)
  - [Keeping your domain free of the bundle](#keeping-your-domain-free-of-the-bundle)
  - [Known limitations](#known-limitations)
- [Custom risk rules](#custom-risk-rules)
- [Commands](#commands)
- [Browser assets](#browser-assets)
  - [IIFE (`assets:install`)](#iife-assetsinstall)
  - [Pentatrion Vite (recommended in Symfony apps)](#pentatrion-vite-recommended-in-symfony-apps)
- [Profiler](#profiler)
- [Translations](#translations)

## Collect endpoint

POST JSON to `/_device/collect` (route `nowo_device_intelligence_collect`):

```json
{
  "v": 1,
  "timestamp": 1710000000,
  "nonce": "3f9c2a7b1e5d4c6a8b0f2e4d6c8a0b1c",
  "sdkVersion": "1.0.0",
  "highEntropyConsent": true,
  "signals": {
    "timezone": { "value": "Europe/Madrid", "quality": 1 },
    "screen": { "value": { "w": 1920, "h": 1080, "dpr": 1 } },
    "canvas": { "value": "abc123digest" }
  }
}
```

`nonce` must match `^[A-Za-z0-9_-]{8,128}$`. Errors: 400 (invalid payload), 403 (origin/CSRF), 409 (replayed nonce), 413 (too large), 429 (rate limited).

The response may include `deviceId`, `confidence`, and `risk` (off by default). An HttpOnly `SameSite=Lax` cookie is set. The browser client in this package builds the payload (IIFE or Pentatrion Vite — see [Browser assets](#browser-assets)).

## Controller argument

```php
use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;

public function dashboard(DeviceContext $device): void
{
    $device->device();
    $device->match();
    $device->risk();
    $device->isNew();
    $device->isTrusted();
}
```

`DeviceContext` is populated on `POST /_device/collect`, and on later requests from the observation cookie (`di_obs` by default — no rematch). Set `observe_on_every_request: true` to always attempt cookie hydration (the demo does this so the Web Profiler toolbar turns green after a reload). The first HTML request has no cookie yet: if the client sends `X-Previous-Debug-Token` (automatic when the toolbar is on the page), opening Device Intelligence on that GET shows the Ajax collect result. You can also open the collect POST in the Ajax tab.

Type the argument as `?DeviceContext $device = null` on pages that may run before collect. A non-nullable `DeviceContext $device` is only safe after hydration.

## Use cases

| Goal | Start here |
| ---- | ---------- |
| Step-up MFA on checkout | [USE-CASES.md §1](USE-CASES.md#1-checkout--payment-step-up) |
| “New browser signed in” | [USE-CASES.md §2](USE-CASES.md#2-login-from-a-new-device) |
| Remember this browser | [USE-CASES.md §3](USE-CASES.md#3-remember-this-device-explicit-trust) |
| Payroll / API tokens | [USE-CASES.md §4](USE-CASES.md#4-privileged-action-on-a-trusted-device) |
| Coupon / trial limits | [USE-CASES.md §5](USE-CASES.md#5-promo--coupon-abuse) |
| Custom score | [USE-CASES.md §7](USE-CASES.md#7-custom-risk-rule) |

Full list and copy-paste controllers: **[USE-CASES.md](USE-CASES.md)**.

## Events

Listen after analyze (the matcher is not mutated):

- `BeforeRiskAssessmentEvent` (immediately before `analyze()`)
- `DeviceObservedEvent`
- `DeviceMatchedEvent` / `DeviceCreatedEvent` / `NewDeviceDetectedEvent`
- `RiskAssessmentCompletedEvent` and alias `DeviceRiskCalculatedEvent`
- `SuspiciousDeviceEvent`
- `DeviceTrustedEvent` / `DeviceRevokedEvent`

## Security hooks

On `LoginSuccessEvent` / `InteractiveLoginEvent` the bundle associates `UserInterface::getUserIdentifier()` with the current device and increments login velocity. Failed logins increment `login_failure`. Logout **keeps** the observation cookie.

## Using it from a DDD / hexagonal application

DeviceIntelligenceBundle is a bounded context of its own (devices, observations, trust, risk) and is already split
along hexagonal lines:

- `Nowo\DeviceIntelligence\` (`lib/`) is the framework-free core: domain objects (`Device`, `DeviceId`,
  `TrustedDevice`, `UserIdentifier`, `RiskAssessment`), services (`DeviceIntelligenceInterface`,
  `DeviceManagerInterface`, `TrustedDeviceManagerInterface`) and ports (`Nowo\DeviceIntelligence\Port\*`). It only
  depends on PSR-20 (clock) and PSR-16 (cache).
- `Nowo\DeviceIntelligenceBundle\` (`src/`) holds the Symfony and Doctrine adapters.

It never references your entities. Users are identified by `UserIdentifier` (a string); the bundle gets it from
your user object through `UserIdentifierResolverInterface`.

### Keeping your domain free of the bundle

- React to device facts with listeners on the bundle events and translate them into your own domain events or
  commands. Payloads are core objects (`Analysis`, `Device`, `UserIdentifier`), not Doctrine entities.

| Event | Payload |
|-------|---------|
| `NewDeviceDetectedEvent`, `DeviceCreatedEvent`, `DeviceMatchedEvent`, `DeviceObservedEvent` | `Analysis $analysis` |
| `RiskAssessmentCompletedEvent` / `DeviceRiskCalculatedEvent`, `SuspiciousDeviceEvent` | `Analysis $analysis` |
| `BeforeRiskAssessmentEvent` | `AnalysisInput $input` |
| `DeviceTrustedEvent`, `DeviceRevokedEvent` | `Device $device`, `UserIdentifier $user` |

```php
#[AsEventListener]
final class NotifyOnNewDevice
{
    public function __construct(private readonly CommandBus $bus) {} // your application bus

    public function __invoke(NewDeviceDetectedEvent $event): void
    {
        $analysis = $event->analysis;
        // map Analysis to your own command; keep Nowo types out of the domain
        $this->bus->dispatch(new WarnAboutNewDevice(/* … */));
    }
}
```

- If your users are not identified by `getUserIdentifier()` (e.g. you want the domain UUID), override the alias
  `Nowo\DeviceIntelligence\User\UserIdentifierResolverInterface` with your resolver
  (`resolve(object $user): UserIdentifier`).
- To keep device data in your own store, implement the four repository ports
  (`DeviceRepositoryInterface`, `ObservationRepositoryInterface`, `DeviceUserRepositoryInterface`,
  `TrustedDeviceRepositoryInterface`) and override their aliases. `doctrine.enabled: false` switches to in-memory
  adapters (tests only, not durable).
- Plug your own infrastructure into the analysis by overriding the alias of a core port; the others keep their
  defaults (the same implementations `DeviceIntelligence::create()` uses):

| Port alias | Default | Typical host adapter |
|------------|---------|----------------------|
| `Nowo\DeviceIntelligence\Port\GeoIpProviderInterface` | `NullGeoIpProvider` (no country) | MaxMind / IP2Location lookup |
| `Nowo\DeviceIntelligence\Port\MetricsRecorderInterface` | `NullMetricsRecorder` | StatsD / Prometheus counters |
| `Nowo\DeviceIntelligence\Device\DeviceLabelerInterface` | `DefaultDeviceLabeler` | your own "Chrome on macOS" labels |
| `Nowo\DeviceIntelligence\Signal\Server\NetworkSignalProviderInterface` | `NullNetworkSignalProvider` | VPN / proxy / ASN signals |
| `Nowo\DeviceIntelligence\Signal\Server\ServerSignalProviderInterface` | `DefaultServerSignalProvider` | extra server-side signals |
| `Nowo\DeviceIntelligence\Privacy\PrivacyProcessorInterface` | `PrivacyProcessor` | stricter signal filtering |
| `Nowo\DeviceIntelligence\Matching\Candidate\DeviceCandidateProviderInterface` | `RepositoryCandidateProvider` | candidates from your own index |

```yaml
# config/services.yaml
services:
    App\Infrastructure\Device\MaxMindGeoIpProvider: ~
    Nowo\DeviceIntelligence\Port\GeoIpProviderInterface: '@App\Infrastructure\Device\MaxMindGeoIpProvider'
```

- Add risk signals from your domain with a `RiskRuleInterface` service (`#[AsDeviceRiskRule]`).
- In controllers, read the request result through the `DeviceContext` argument or the `#[DeviceRisk]` /
  `#[RequireTrustedDevice]` attributes rather than calling the core directly.

### Known limitations

- `ConsentGateInterface` and `IpReputationProviderInterface` are not used yet.
- The Doctrine mapping is registered even with `doctrine.enabled: false`, and Doctrine ORM is a required package.
- `DeviceTrustService` (the trust API that dispatches `DeviceTrustedEvent`/`DeviceRevokedEvent`) is final and has
  no interface; wrap it behind your own port.

## Custom risk rules

```php
use Nowo\DeviceIntelligence\Risk\RiskRuleInterface;
use Nowo\DeviceIntelligenceBundle\Attribute\AsDeviceRiskRule;

#[AsDeviceRiskRule(priority: 10)]
final class VipAllowlistRule implements RiskRuleInterface
{
    public function name(): string { return 'vip_allowlist'; }
    public function evaluate(\Nowo\DeviceIntelligence\Risk\RiskContext $context): \Nowo\DeviceIntelligence\Risk\RiskResult
    {
        return new \Nowo\DeviceIntelligence\Risk\RiskResult(0, $this->name());
    }
}
```

## Commands

```bash
php bin/console device-intelligence:device:show 01ARZ3NDEKTSV4RRFFQ69G5FAV
php bin/console device-intelligence:user:devices alice@example.test
php bin/console device-intelligence:risk:test --file=signals.json
php bin/console device-intelligence:cleanup --older-than=P180D
php bin/console device-intelligence:stats
php bin/console device-intelligence:recalculate
```

## Browser assets

**pnpm + Vite** live in the bundle (`packageManager: pnpm@10.32.1`). `make assets` builds the published IIFE.

### IIFE (`assets:install`)

The bundle registers the Symfony asset package `nowo_device_intelligence` (`base_path: /bundles/nowodeviceintelligence`). After `php bin/console assets:install`:

```twig
<script src="{{ asset('js/device-intelligence.min.js', 'nowo_device_intelligence') }}"></script>
<script>
  const device = new DeviceIntelligence({ endpoint: '/_device/collect' });
  device.collect();
</script>
```

### Pentatrion Vite (recommended in Symfony apps)

Compile the TypeScript sources with `vite-plugin-symfony` + `pentatrion/vite-bundle` (the demo does this):

```ts
import { DeviceIntelligence } from '@bundle/src/index.ts';

const device = new DeviceIntelligence({ endpoint: '/_device/collect' });
device.collect();
```

```twig
{{ vite_entry_script_tags('app') }}
```

Point the Vite alias `@bundle` at `vendor/nowo-tech/device-intelligence-bundle/src/Resources/assets` (or the path repository mount).

## Profiler

Panel id: `nowo_device_intelligence`. Twig namespace: `NowoDeviceIntelligenceBundle`.

## Translations

Domain: **`NowoDeviceIntelligenceBundle`**. Required locales: `en`, `es`, `it`, `fr`, `pt`, `de`, `nl`.

The catalogues cover the Web Profiler panel. Collect JSON, console output, and risk reason ids stay untranslated.

Override keys in `translations/NowoDeviceIntelligenceBundle.<locale>.yaml` in the host app:

```yaml
# translations/NowoDeviceIntelligenceBundle.es.yaml
profiler:
    title: 'Inteligencia de dispositivo'
```
