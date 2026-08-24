# Usage

Copy-paste scenarios (checkout step-up, new-device login, trusted devices, coupons, custom rules): **[USE-CASES.md](USE-CASES.md)**.

## Table of contents

- [Collect endpoint](#collect-endpoint)
- [Controller argument](#controller-argument)
- [Use cases](#use-cases)
- [Events](#events)
- [Security hooks](#security-hooks)
- [Custom risk rules](#custom-risk-rules)
- [Commands](#commands)
- [Browser assets](#browser-assets)
- [Profiler](#profiler)
- [Translations](#translations)

## Collect endpoint

POST JSON to `/_device/collect` (route `nowo_device_intelligence_collect`):

```json
{
  "v": 1,
  "timestamp": 1710000000,
  "nonce": "random-unique-string",
  "sdkVersion": "1.0.0",
  "highEntropyConsent": true,
  "signals": {
    "timezone": { "value": "Europe/Madrid", "quality": 1 },
    "screen": { "value": { "w": 1920, "h": 1080, "dpr": 1 } },
    "canvas": { "value": "abc123digest" }
  }
}
```

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
