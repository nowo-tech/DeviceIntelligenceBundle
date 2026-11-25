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
- **FR-CORE-002**: Optional atomic repository ports (1.10, REQ-RUNTIME-003 rule 4): `AtomicDeviceRepositoryInterface` (`saveObserved()` increments the observation count in storage and never reverts a concurrent status change; `saveStability()`), `AtomicDeviceUserRepositoryInterface::recordLogin()` and `AtomicTrustedDeviceRepositoryInterface::revoke()`. When an adapter implements one, the analysis, `DeviceManager::associate()` and `revoke()` use it; otherwise they fall back to read-then-save (keeping the stored status since 1.10.1), which narrows but does not close the race.
- **FR-CORE-003**: `LookAlikeIdentifierException` (REQ-SEC-006, 1.9.2): a write blocked by the row of a look-alike identifier on a folding unique key fails closed (never updates the look-alike's row); the atomic user ports declare it.
- **FR-MATCH-001**: Weighted matcher; no `sha256(all_signals)` identity.
- **FR-RISK-001**: Built-in rules + `#[AsDeviceRiskRule]` tagged custom rules.
- **FR-RISK-002**: `IpReputationRule` (`ip_reputation`, 1.7) scores the `IpReputation` of the client IP from `IpReputationProviderInterface`: Tor 30, proxy 20, VPN 10, hosting 10 points or the provider risk (0..100) divided by 3, whichever is larger, capped at 40 (severity High from 30, else Medium); 0 without a provider (the default `NullIpReputationProvider`).

### HTTP

- **FR-CTRL-001**: `CollectController` `#[Route('/_device/collect')]` POST only.
- **FR-HTTP-001**: Origin / double-submit CSRF, payload cap, schema `v=1`, nonce replay via PSR-16.

### Persistence & messenger

- **FR-ORM-001**: Device, observation, device-user, trust entities.
- **FR-ORM-002**: (1.7) Bundle entities live on `doctrine.entity_manager` only: `DeviceIntelligenceEntityManagerPass` registers `ExcludedEntityNamespaceDriver` (maps nothing) for the bundle namespace on every other manager, and `DbalEntityWriter` writes one entity with a DBAL INSERT / UPDATE from its metadata, so request listeners and login never flush the host's entity manager.
- **FR-ORM-003**: (1.9.2, REQ-SEC-006) `ExactCollationListener` gives `user_identifier` / `device_id` of `device_trust`, `device_user` and `observation` a byte-exact collation on MySQL / MariaDB; `PersonalDataRows` re-checks every row with `===` after the SQL pre-filter.
- **FR-ORM-004**: (1.10, REQ-RUNTIME-003 rules 1, 3 and 9) `DeviceIntelligenceTransactionRunner` runs the analysis write unit (device and observation rows) in one DBAL-only transaction on the connection of the manager that maps the bundle entities (resolved per call, no flush or reset of any entity manager); inside a host transaction it uses an explicit SAVEPOINT, a bounded lock wait keeps it from hanging the request, and a deadlock, lock-wait timeout or serialization failure is rethrown as the contract's `TransientStorageFailure` (nothing committed, one attempt, no retry).
- **FR-TRUST-001**: (1.7) `DeviceTrustServiceInterface` is the trust API port (trust, revoke, is-trusted); implementations dispatch the trust events.
- **FR-MSG-001**: `CleanupHandler` / `RecalculateStabilityHandler` with `#[AsMessageHandler]`; retry and `failure_transport` are host-owned (`framework.messenger`).

### Twig & profiler

- **FR-TWIG-001**: Namespace `NowoDeviceIntelligenceBundle`; profiler template overridable via `templates/bundles/NowoDeviceIntelligenceBundle/`.
- **FR-PROF-001**: Data collector id `nowo_device_intelligence`; truncated signal summaries only.

### Privacy

- **FR-PRIV-001**: Default `privacy.hash_ip: true`; raw IP not stored; logs never include full signal bags.

### Personal data (GDPR, 1.9)

- **FR-GDPR-001**: `DeviceIntelligencePersonalDataEraserInterface` (art. 17: observations, device-user links, trust grants, then devices nothing else references) and `DeviceIntelligencePersonalDataExporterInterface` (art. 15 / 20, read-only arrays) take a `DeviceIntelligencePersonalDataSubject`; defaults run on the bundle's entity manager, `DeviceIntelligenceErasureResult` counts deleted rows, `DeviceIntelligencePersonalDataErasedEvent` fires after commit only when the erasure ran in its own transaction; `UserIdentifierSubjectResolver` is the default `DeviceIntelligencePersonalDataSubjectResolverInterface`.
- **FR-GDPR-002**: UserKit bridge: `DeviceIntelligenceUserKitEraser` and `DeviceIntelligenceUserKitExportProvider` (section `devices`) register when user-kit-bundle is installed and `personal_data.enabled`; `PersonalDataBridgePass` keeps them only when their port is bound.

### Guards & CLI

- **FR-GUARD-001**: `#[DeviceRisk]`, `#[RequireTrustedDevice]`, `#[DeviceRateLimit]` fail closed (403).
- **FR-CLI-001**: Console commands for show/stats/cleanup/recalculate/risk-test/user-devices.

### Events & request context

- **FR-EVT-001**: `AnalyzeService` dispatches observe/match/created/risk events; subscribers do not mutate the matcher.
- **FR-CTX-001**: `_device` is set on collect and hydrated from the observation cookie on later requests (`observe_on_every_request` optional).

### Long-running workers (FrankenPHP)

- **FR-WORKER-001**: Bundle-owned per-request state is cleared at the start of every main request (`RequestStateResetSubscriber` + `kernel.reset`). Doctrine repositories resolve the manager per call (reset when closed), refresh managed entities on read, and detach after mapping so trust/status changes from other workers are visible without a kernel reset.

### i18n

- **FR-I18N-001**: Domain `NowoDeviceIntelligenceBundle`; locales `en`, `es`, `it`, `fr`, `pt`, `de`, `nl` with key parity (profiler UI only).

### Frontend (TypeScript)

- **FR-TS-001**: Browser SDK collectors + fetch transport; `collect()` never throws (`ok: false`, `degraded: true`).
- **FR-TS-002**: `index.ts` creates the logger (`createBundleLogger('device-intelligence', { buildTime })`) and prints one `📦 [device-intelligence] script loaded, build: <date> UTC` console line (readable build label, REQ-ASSETS-005); `<html data-nowo-console="quiet">` or `globalThis.NOWO_CONSOLE = 'quiet'` silences it.
- **FR-BUILD-001**: Vite IIFE `src/Resources/public/js/device-intelligence.min.js`.
- **FR-TEST-TS-001**: Vitest under `src/Resources/assets/tests/` with ≥90% line coverage (excluded from inventory count; 9 files).

---

## Success Criteria

- **SC-001**: Production inventory **233/233** (`specs/001-baseline/code-inventory.md`).
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
