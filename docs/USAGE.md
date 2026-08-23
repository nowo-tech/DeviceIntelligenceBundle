# Usage

## Table of contents

- [Collect endpoint](#collect-endpoint)
- [Controller argument](#controller-argument)
- [Events](#events)
- [Security hooks](#security-hooks)
- [Custom risk rules](#custom-risk-rules)
- [Commands](#commands)
- [Profiler](#profiler)

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

The response may include `deviceId`, `confidence`, and `risk` (off by default). An HttpOnly `SameSite=Lax` cookie is set. The browser IIFE in this package builds the payload (`asset('js/device-intelligence.min.js', 'nowo_device_intelligence')`).

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

`DeviceContext` is populated after collect, or on every request when `observe_on_every_request: true` (from the observation cookie — no rematch).

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

## Profiler

Panel id: `nowo_device_intelligence`. Twig namespace: `NowoDeviceIntelligenceBundle`.
