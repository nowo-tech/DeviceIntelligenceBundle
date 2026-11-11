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
  - [Standard host or DDD host without Doctrine (1.8)](#standard-host-or-ddd-host-without-doctrine-18)
  - [Known limitations](#known-limitations)
- [GDPR erasure and export](#gdpr-erasure-and-export)
  - [UserKit bridge](#userkit-bridge)
  - [What is and is not erased](#what-is-and-is-not-erased)
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
  `TrustedDeviceRepositoryInterface`) and override their aliases (see
  [Standard host or DDD host without Doctrine](#standard-host-or-ddd-host-without-doctrine-18)). `doctrine.enabled:
  false` without your own adapters falls back to in-memory ones (tests only, not durable).
- Plug your own infrastructure into the analysis by overriding the alias of a core port; the others keep their
  defaults (the same implementations `DeviceIntelligence::create()` uses):

| Port alias | Default | Typical host adapter |
|------------|---------|----------------------|
| `Nowo\DeviceIntelligence\Port\GeoIpProviderInterface` | `NullGeoIpProvider` (no country) | MaxMind / IP2Location lookup |
| `Nowo\DeviceIntelligence\Port\MetricsRecorderInterface` | `NullMetricsRecorder` | StatsD / Prometheus counters |
| `Nowo\DeviceIntelligence\Device\DeviceLabelerInterface` | `DefaultDeviceLabeler` | your own "Chrome on macOS" labels |
| `Nowo\DeviceIntelligence\Signal\Server\NetworkSignalProviderInterface` | `NullNetworkSignalProvider` | VPN / proxy / ASN signals |
| `Nowo\DeviceIntelligence\Signal\Server\ServerSignalProviderInterface` | `DefaultServerSignalProvider` | extra server-side signals |
| `Nowo\DeviceIntelligence\Privacy\PrivacyProcessorInterface` | `PrivacyProcessor` | a decorator for stricter filtering (see below) |
| `Nowo\DeviceIntelligence\Matching\Candidate\DeviceCandidateProviderInterface` | `RepositoryCandidateProvider` | candidates from your own index |

```yaml
# config/services.yaml
services:
    App\Infrastructure\Device\MaxMindGeoIpProvider: ~
    Nowo\DeviceIntelligence\Port\GeoIpProviderInterface: '@App\Infrastructure\Device\MaxMindGeoIpProvider'
```

  Rules for these adapters:

  - **Privacy processor: decorate, don't replace.** The default `PrivacyProcessor` is what drops the high-entropy
    signals (`audio`, `canvas`, `webgl`, `fonts`) in `strict` mode or without high-entropy consent, and the
    `user_agent` signal when `store_user_agent` is false. A replacement takes over that filtering of the stored
    signals entirely. Decorate the default instead and call it first, then filter more:

    ```php
    use Nowo\DeviceIntelligence\Privacy\PrivacyContext;
    use Nowo\DeviceIntelligence\Privacy\PrivacyProcessorInterface;
    use Nowo\DeviceIntelligence\Signal\SignalBag;
    use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
    use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

    #[AsDecorator(PrivacyProcessorInterface::class)]
    final readonly class StricterPrivacyProcessor implements PrivacyProcessorInterface
    {
        public function __construct(#[AutowireDecorated] private PrivacyProcessorInterface $inner) {}

        public function process(SignalBag $signals, PrivacyContext $context): SignalBag
        {
            $signals = $this->inner->process($signals, $context); // keep the bundle's consent / strict / UA rules

            return $signals; // then drop whatever else your policy forbids
        }
    }
    ```

    The IP is hashed and the raw user-agent column is gated by `store_user_agent` outside the processor, so neither
    depends on it.
  - **GeoIP and network-signal adapters receive the raw client IP.** Never return it (or anything derived that
    identifies it) in a signal, and don't log it: network-signal values are stored as returned, so an IP in a signal
    defeats `hash_ip`.
  - **Adapters are shared services.** In a long-running worker (FrankenPHP, RoadRunner) an adapter with an in-memory
    cache or a buffer lives for the whole worker: implement `Symfony\Contracts\Service\ResetInterface` to clear it
    between requests.

- Add risk signals from your domain with a `RiskRuleInterface` service (`#[AsDeviceRiskRule]`).
- In controllers, read the request result through the `DeviceContext` argument or the `#[DeviceRisk]` /
  `#[RequireTrustedDevice]` attributes rather than calling the core directly.

- **Persistence (1.7):** the Doctrine repositories never flush an entity manager: each row is written with a DBAL
  `INSERT` / `UPDATE` (inside a SAVEPOINT when your code has a transaction open), so an analysis on login or in a
  request listener never commits your pending changes. To keep the tables on their own connection, set
  `nowo_device_intelligence.doctrine.entity_manager: device_intelligence` (mapped on that manager only, also when your
  default manager has `auto_mapping: true`). With `doctrine.enabled: false` the entities are mapped nowhere and you bind
  the core repository ports (`DeviceRepositoryInterface`, …) to your own adapters; DoctrineBundle is then not needed.
- **Consent and reputation (1.7):** alias `Nowo\DeviceIntelligence\Privacy\ConsentGateInterface` to your consent
  service (asked per collector: `canvas`, `webgl`, `audio`, `fonts`, …) and
  `Nowo\DeviceIntelligence\Port\IpReputationProviderInterface` to your reputation source (feeds the `ip_reputation`
  risk rule).
- **Trust API (1.7):** type-hint `DeviceTrustServiceInterface` (trust / revoke / isTrusted; dispatches
  `DeviceTrustedEvent` / `DeviceRevokedEvent`).
- A "host controls the transaction" mode is not needed: the bundle writes single rows without flushing, and they
  join your transaction when one is open.

### Standard host or DDD host without Doctrine (1.8)

- **Standard path (default, nothing to configure).** DoctrineBundle with `doctrine.orm`: the bundle maps its four
  entities (on the default manager, or on `doctrine.entity_manager`) and writes them with DBAL, never flushing yours.
- **DDD path (opt-in).** No Doctrine ORM in the application: turn the bundle's persistence off and bind the four
  repository ports to your own adapters. The configuration itself never needs the ORM.

```yaml
# config/packages/nowo_device_intelligence.yaml — DoctrineBundle not registered
nowo_device_intelligence:
    doctrine:
        enabled: false

# config/services.yaml — your aliases win over the bundle defaults
services:
    Nowo\DeviceIntelligence\Port\DeviceRepositoryInterface: '@App\Infrastructure\Device\DeviceStore'
    Nowo\DeviceIntelligence\Port\ObservationRepositoryInterface: '@App\Infrastructure\Device\ObservationStore'
    Nowo\DeviceIntelligence\Port\DeviceUserRepositoryInterface: '@App\Infrastructure\Device\DeviceUserStore'
    Nowo\DeviceIntelligence\Port\TrustedDeviceRepositoryInterface: '@App\Infrastructure\Device\TrustStore'
```

Without Doctrine ORM and with `doctrine.enabled` left at `true`, the build fails with a message that says exactly
this (1.8; before, the mapping pass failed with "Could not find the manager name parameter"). Your adapters are shared
services: in a kept-alive FrankenPHP worker they serve every request, so keep no request or user state in them (or
implement `ResetInterface` and clear it yourself at the request boundary). The kernel test
`tests/Integration/Ddd/HostAdaptersKernelTest.php` runs this setup through `http_kernel` for 600 requests and two
users without the services resetter.

### Known limitations

- Doctrine ORM is still a required package (installed, not necessarily configured); it moves to `suggest` in 2.0 (the
  bundle already runs without DoctrineBundle with `doctrine.enabled: false`).

## GDPR erasure and export

Since 1.9 the bundle erases and exports one person's device data through two ports (plain PHP, no Doctrine or HTTP
types in their signatures), registered with `doctrine.enabled: true` (a host without Doctrine binds its own services
to the interfaces):

| Service (interface id) | Method |
|---|---|
| `Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataEraserInterface` | `erase(DeviceIntelligencePersonalDataSubject): DeviceIntelligenceErasureResult` |
| `Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataExporterInterface` | `export(DeviceIntelligencePersonalDataSubject): list<array<string, mixed>>` |

```php
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataSubject;

$subject = DeviceIntelligencePersonalDataSubject::fromUserIdentifier($user->getUserIdentifier());
$rows = $exporter->export($subject);   // JSON-serialisable rows, one per device
$result = $eraser->erase($subject);     // counts per kind
$result->toArray();                     // ['observations' => 3, 'device_users' => 3, 'device_trusts' => 2, 'devices' => 2]
$result->total();                       // 10
```

- **Subject**: the user identifier as the bundle stores it in `user_identifier` (trimmed like on write, no case
  folding); empty, longer than 191 bytes or with a NUL byte is rejected (`InvalidArgumentException`).
- **Exact matching**: the SQL `=` only pre-filters; the stored value is re-checked with `===` in PHP and rows are
  deleted by primary key in batches of 500. On a MySQL `*_ci` / `*_ai_ci` collation, `Alice` and `alíce` are never
  erased or exported for `alice`.
- **Idempotent**: a second `erase()` returns zero counts.
- **Transactions**: with no transaction open on the bundle's connection, `erase()` runs in its own transaction and
  dispatches `Nowo\DeviceIntelligenceBundle\Event\DeviceIntelligencePersonalDataErasedEvent` (subject, counts,
  `erasedAt`) after the commit. Inside your own transaction it joins it and **the event is not dispatched**; the
  returned result is the outcome. A host transaction on another connection (dedicated `doctrine.entity_manager`) is
  not the bundle's.
- **Export rows**: `first_seen_at`, `last_seen_at`, `sign_ins`, `os_family`, `browser_family`, `trusted`,
  `trusted_at`, `trust_expires_at` (ATOM strings or `null`). Never device ids, fingerprints, signals, IP hashes,
  session ids or raw user agents.
- **Worker mode**: both services are stateless (`readonly`); a test runs erase/export for two users 1,000 times in one
  instance.

### UserKit bridge

With `nowo-tech/user-kit-bundle` installed and `personal_data.enabled: true` (default), the bundle registers:

| UserKit SPI | Key | Priority |
|---|---|---|
| `nowo_user_kit.personal_data.eraser` | `device_intelligence` | 150 (before the host identity eraser, REQ-DATA-002) |
| `nowo_user_kit.personal_data.export_provider` | `devices` | 35 |

The eraser erases every distinct subject of the user: the original identifier (UserKit ≥ 1.12
`ErasureRequest::$originalIdentifier`), the current `getUserIdentifier()`, and `context['previous_email']` when it is
a non-empty string. Replace that list with `personal_data.subject_resolver` (a service implementing
`DeviceIntelligencePersonalDataSubjectResolverInterface`). An adapter is not registered when its port is not bound
(e.g. `doctrine.enabled: false` without your own alias). Set `personal_data.enabled: false` if your application
already erases these tables.

### What is and is not erased

| Table | Erased |
|---|---|
| `observation` | every observation of the person (raw user agent, IP hash, session id, signals) |
| `device_user` | the person's links |
| `device_trust` | the person's trust grants (active, expired or revoked) |
| `device` | only devices of the person that no link, trust grant or observation (anyone's, anonymous included) still references |

Anonymous observations (no user identifier) cannot be tied to a person and are kept; they expire with the retention
cleanup (`CleanupMessage` / the cleanup command).

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
