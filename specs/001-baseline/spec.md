# Feature Specification: DeviceIntelligenceBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Status**: Active  

**Package**: `nowo-tech/device-intelligence-bundle`  
**Configuration root**: `nowo_device_intelligence`  
**Code inventory**: [`code-inventory.md`](code-inventory.md)

---

## Summary

Probabilistic **device intelligence** for Symfony: weighted matching (not a monolithic fingerprint hash), risk scoring, collect endpoint, Doctrine persistence, Security hooks, trusted devices, and first-party browser collectors. A Device ID is **not** a credential.

---

## User Scenarios

### US-01 — Collect and match (P1)

**Given** a browser POSTs protocol v1 signals to `/_device/collect`, **When** origin, size, schema, timestamp, and nonce checks pass, **Then** the engine returns an observation token and may match an existing device (Chrome 143→144 does not create a new Device).

### US-02 — Risk and step-up (P1)

**Given** an analysis, **When** risk rules run, **Then** the host receives score/level/reasons and may use `#[DeviceRisk]` / `#[RequireTrustedDevice]` for 403 guards — never as login.

### US-03 — Persistence (P1)

**Given** `doctrine.enabled: true`, **When** devices and observations are saved, **Then** tables use `doctrine.table_prefix` (default `device_intelligence_`).

### US-04 — Profiles (P1)

**Given** `default_profile` and `profiles`, **When** YAML is processed, **Then** collectors, matching weights (~1.0), risk, and privacy apply from the selected profile; unknown collector names are rejected.

### US-05 — Privacy (P1)

**Given** default privacy, **When** an observation is stored, **Then** IP is hashed, raw IP is not stored, and logs never include full signal bags.

### US-06 — Browser SDK (P2)

**Given** the IIFE `device-intelligence.min.js`, **When** `collect()` runs, **Then** failures resolve to `{ ok: false, degraded: true }` and do not throw into application code.

---

## Requirements

### Bundle & config

- **FR-BUNDLE-001**: `NowoDeviceIntelligenceBundle` alias `nowo_device_intelligence`.
- **FR-CFG-001**: Named `profiles` + `default_profile`; legacy flat keys normalize into `profiles.default`.
- **FR-CFG-002**: Matching weights must sum to ~1.0; `candidate_limit` ≤ 64.
- **FR-DI-001**: `src/Resources/config/services.php` wires adapters; `DeviceIntelligence` is not shared.
- **FR-DI-002**: `SystemClock` implements `Psr\Clock\ClockInterface` when the host does not provide a clock.
- **FR-ASSETS-001**: Prepend `framework.assets.packages.nowo_device_intelligence` (`base_path: /bundles/nowodeviceintelligence`).

### Matching & risk (core `lib/`)

- **FR-CORE-001**: Device/observation/trust/user value objects, ports, and in-memory adapters.
- **FR-MATCH-001**: Weighted matcher; no `sha256(all_signals)` identity.
- **FR-RISK-001**: Built-in rules + `#[AsDeviceRiskRule]` tagged custom rules.

### HTTP

- **FR-CTRL-001**: `CollectController` `#[Route('/_device/collect')]` POST only.
- **FR-HTTP-001**: Origin / double-submit CSRF, payload cap, schema `v=1`, nonce replay via PSR-16.

### Persistence & messenger

- **FR-ORM-001**: Device, observation, device-user, trust entities.
- **FR-MSG-001**: `CleanupHandler` / `RecalculateStabilityHandler` with `#[AsMessageHandler]`; retry and `failure_transport` are host-owned (`framework.messenger`).

### Twig & profiler

- **FR-TWIG-001**: Namespace `NowoDeviceIntelligenceBundle`; profiler template overridable via `templates/bundles/NowoDeviceIntelligenceBundle/`.
- **FR-PROF-001**: Data collector id `nowo_device_intelligence`; truncated signal summaries only.

### Privacy

- **FR-PRIV-001**: Default `privacy.hash_ip: true`; raw IP not stored; logs never include full signal bags.

### Guards & CLI

- **FR-GUARD-001**: `#[DeviceRisk]`, `#[RequireTrustedDevice]`, `#[DeviceRateLimit]` fail closed (403).
- **FR-CLI-001**: Console commands for show/stats/cleanup/recalculate/risk-test/user-devices.

### Events & request context

- **FR-EVT-001**: `AnalyzeService` dispatches observe/match/created/risk events; subscribers do not mutate the matcher.
- **FR-CTX-001**: `_device` is set on collect and hydrated from the observation cookie on later requests (`observe_on_every_request` optional).

### i18n

- **FR-I18N-001**: Domain `NowoDeviceIntelligenceBundle`; locales `en`, `es`, `it`, `fr`, `pt`, `de`, `nl` with key parity (profiler UI only).

### Frontend (TypeScript)

- **FR-TS-001**: Browser SDK collectors + fetch transport; `collect()` never throws (`ok: false`, `degraded: true`).
- **FR-BUILD-001**: Vite IIFE `src/Resources/public/js/device-intelligence.min.js`.
- **FR-TEST-TS-001**: Vitest under `src/Resources/assets/tests/` with ≥90% line coverage (excluded from inventory count).

---

## Success Criteria

- **SC-001**: Production inventory **204/204** (`specs/001-baseline/code-inventory.md`).
- **SC-002**: Config keys match `docs/CONFIGURATION.md`.
- **SC-003**: `make qa` / PHPUnit / PHPStan / Vitest pass in CI.

---

## Key entities

| Entity | Role |
| --- | --- |
| Device | Probable returning browser cluster (ULID). Not a credential. |
| Observation | One collect event + compact derived signals. |
| DeviceMatch | Weighted similarity vs candidates (not `sha256(all_signals)`). |
| RiskResult | Score, level, reasons, suggested action. |
| Trust | Explicit user–device grant after an integrator action (never automatic). |
| Profile | Named YAML config (`collectors`, matching weights, risk, privacy). |

## Assumptions

- Host apps apply authorization voters / MFA independently of `#[DeviceRisk]` / `#[RequireTrustedDevice]`.
- Collect is a first-party same-origin POST; `csrf: none` is only for a trusted gateway.
- Doctrine tables use `doctrine.table_prefix` (default `device_intelligence_`) when persistence is enabled.
- Browser collectors run in the host page; the Composer package ships the IIFE, not a public npm SDK.

## Explicit non-goals

- Device ID as authentication or MFA.
- Legal/GDPR compliance guarantees (integrator responsibility).
- Public npm package (collectors ship inside this Composer package).

---

## Validation

`make qa`, `pnpm test`, PHPUnit, PHPStan, inventory audit.

## See also

- [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [`docs/SPEC-KIT.md`](../../docs/SPEC-KIT.md)
- [`docs/USAGE.md`](../../docs/USAGE.md)
- [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md)
- [`docs/SECURITY.md`](../../docs/SECURITY.md)
