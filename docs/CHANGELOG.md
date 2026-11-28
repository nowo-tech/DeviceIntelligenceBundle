# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.11.1] - 2026-11-26](#1111---2026-11-26)
  - [Fixed](#fixed)
  - [Changed](#changed)
- [[1.11.0] - 2026-11-23](#1110---2026-11-23)
  - [Fixed](#fixed-1)
  - [Changed](#changed-1)
  - [Added](#added)
- [[1.10.3] - 2026-11-21](#1103---2026-11-21)
  - [Changed](#changed-2)
  - [Fixed](#fixed-2)
- [[1.10.2] - 2026-11-19](#1102---2026-11-19)
  - [Changed](#changed-3)
  - [Fixed](#fixed-3)
- [[1.10.1] - 2026-11-19](#1101---2026-11-19)
  - [Security](#security)
  - [Changed](#changed-4)
  - [Fixed](#fixed-4)
- [[1.10.0] - 2026-11-19](#1100---2026-11-19)
  - [Added](#added-1)
  - [Changed](#changed-5)
  - [Fixed](#fixed-5)
- [[1.9.3] - 2026-11-16](#193---2026-11-16)
  - [Security](#security-1)
  - [Changed](#changed-6)
  - [Documentation](#documentation)
- [[1.9.2] - 2026-11-13](#192---2026-11-13)
  - [Security](#security-2)
- [[1.9.1] - 2026-11-13](#191---2026-11-13)
  - [Fixed](#fixed-6)
  - [Changed](#changed-7)
- [[1.9.0] - 2026-11-11](#190---2026-11-11)
  - [Added](#added-2)
  - [Behaviour change](#behaviour-change)
- [[1.8.0] - 2026-11-11](#180---2026-11-11)
  - [Changed](#changed-8)
  - [Tests](#tests)
  - [Documentation](#documentation-1)
- [[1.7.0] - 2026-11-11](#170---2026-11-11)
  - [Security](#security-3)
  - [Added](#added-3)
  - [Changed](#changed-9)
  - [Deprecated](#deprecated)
- [[1.6.0] - 2026-11-10](#160---2026-11-10)
  - [Added](#added-4)
  - [Documentation](#documentation-2)
- [[1.5.2] - 2026-10-31](#152---2026-10-31)
  - [Fixed](#fixed-7)
  - [Changed](#changed-10)
  - [Tests](#tests-1)
- [[1.5.1] - 2026-10-30](#151---2026-10-30)
  - [Security](#security-4)
- [[1.5.0] - 2026-10-28](#150---2026-10-28)
  - [Changed](#changed-11)
  - [Documentation](#documentation-3)
- [[1.4.1] - 2026-10-25](#141---2026-10-25)
  - [Changed](#changed-12)
- [[1.4.0] - 2026-10-21](#140---2026-10-21)
  - [Changed](#changed-13)
- [[1.3.3] - 2026-10-21](#133---2026-10-21)
  - [Changed](#changed-14)
- [[1.3.2] - 2026-10-15](#132---2026-10-15)
  - [Changed](#changed-15)
- [[1.3.1] - 2026-10-13](#131---2026-10-13)
  - [Changed](#changed-16)
- [[1.3.0] - 2026-10-13](#130---2026-10-13)
  - [Security](#security-5)
  - [Fixed](#fixed-8)
  - [Changed](#changed-17)
- [[1.2.2] - 2026-10-13](#122---2026-10-13)
  - [Dependencies](#dependencies)
  - [Changed](#changed-18)
- [[1.2.1] - 2026-10-09](#121---2026-10-09)
  - [Fixed](#fixed-9)
  - [Dependencies](#dependencies-1)
- [[1.2.0] - 2026-09-28](#120---2026-09-28)
  - [Security](#security-6)
  - [Changed](#changed-19)
- [[1.1.5] - 2026-09-27](#115---2026-09-27)
  - [Added](#added-5)
  - [Changed](#changed-20)
- [[1.1.4] - 2026-09-24](#114---2026-09-24)
  - [Fixed](#fixed-10)
  - [Documentation](#documentation-4)
- [[1.1.3] - 2026-09-07](#113---2026-09-07)
  - [Changed](#changed-21)
  - [Notes](#notes)
- [[1.1.2] - 2026-09-03](#112---2026-09-03)
  - [Fixed](#fixed-11)
  - [Changed](#changed-22)
- [[1.1.1] - 2026-08-26](#111---2026-08-26)
  - [Changed](#changed-23)
  - [Notes](#notes-1)
- [[1.1.0] - 2026-08-24](#110---2026-08-24)
  - [Added](#added-6)
  - [Changed](#changed-24)
  - [Notes](#notes-2)
  - [Added](#added-7)
  - [Documentation](#documentation-5)
  - [Fixed](#fixed-12)
- [[1.0.1] - 2026-08-23](#101---2026-08-23)
  - [Added](#added-8)
  - [Changed](#changed-25)
  - [Fixed](#fixed-13)
  - [Documentation](#documentation-6)
- [[1.0.0] - 2026-08-23](#100---2026-08-23)
  - [Added](#added-9)
  - [Documentation](#documentation-7)

## [Unreleased]

### Changed

- PHP 8.6: `composer.json` allows PHP below 8.7 (was below 8.6). CI adds a forward-compatibility workflow with a
  PHP 8.6 cell and a Symfony 8.2 cell (stable Symfony 8.2.0).

## [1.11.1] - 2026-11-26

### Fixed

- The `📦 [name] script loaded` console banner now prints once per page, even when the script is loaded twice: per-name once-guard (a `Set` on `globalThis`) in `logger.ts`, with a Vitest test and rebuilt assets (REQ-ASSETS-005).

### Changed

- CI (not shipped): the Dependabot asset rebuild (`.github/workflows/dependabot-assets.yml`, was `dependabot.yml`) is split into a read-only build job (no persisted credentials, `pnpm install --frozen-lockfile --ignore-scripts`) and a commit job, the only one with write access, that never installs or builds: it accepts only regular files under the asset paths from the build artifact, commits them and dispatches `ci.yml` (now `workflow_dispatch`-able) so the required checks run on the rebuilt head (audits ZAWD-M1, ZAWD-L2). It now runs on Dependabot npm branches only.

## [1.11.0] - 2026-11-23

### Fixed

- `make validate-phpdoc` passes again: the 14 public methods that had no docblock (the excluded-namespace Doctrine driver, `TwigPathsPass`, the personal-data eraser/exporter/subject resolver, the UserKit adapters, `DeviceTrustServiceInterface::isTrusted()`) now carry one, with the `@return` shape on the array-returning ones. Documentation only, no code change.

### Changed

- Igor 0.11: the PSR-16 `cache` of `SymfonyDeviceRateLimiter` is marked `#[WorkerSafe]` with the reason (a documented false positive: the pool is the limiter's shared counter store by design; only the in-memory fallback is per request and `reset()` clears it). No behaviour change.
- CI: new `release-gates` job in `.github/workflows/ci.yml` runs the `make release-check` gates (`rector-dry`, `validate-phpdoc`, `validate-translations`, `check-twig-extra`) on every full-mode push and PR, so they no longer fail only at release time. CI only, no bundle change.
- `rector.php` skips `EventSubscriberToAttributeRector` for the five `src/EventSubscriber/*` classes, with the reason written in the file: the rule would remove the public `getSubscribedEvents()` and the `EventSubscriberInterface` (a BC break), which does not ship in a minor. Revisit at the next major.
- **Console**: the script-loaded line of `device-intelligence.js` now reads `📦 [device-intelligence] script loaded, build: 2026-11-23 14:05 UTC`: a readable UTC build time instead of the opaque source hash. The time comes from the committed `build-info.json` (`{sourceHash, builtAt}`): the source hash stays the change detector, `builtAt` is reused while the sources are unchanged and renewed only when they change, so rebuilding unchanged sources never rewrites the committed assets (no wall-clock stamp, no git date).

### Added

- **Console**: quiet switch for the script-loaded line: `<html data-nowo-console="quiet">` or `globalThis.NOWO_CONSOLE = 'quiet'` prints nothing. The default is unchanged.

## [1.10.3] - 2026-11-21

### Changed

- Requires `nowo-tech/transaction-doctrine` ^1.8.1 (fixes GHSA-9wh3-m3m5-jffx and GHSA-65w2-9r4h-848g).
- Demo: runs in UiKit's demo platform shell (`@NowoUiKit/demo_platform/layout.html.twig`, UiKit ^2.60): the demo declares itself in `templates/base.html.twig` (every example is an aside item, sign-in and user menu and locale pills from the shell), the pages use the page header and section cards, the demo's own masthead, the example pills and the `<style>` of the device id tile are gone, a demo-platform e2e spec checks the layout rules, the drawer and axe, and the README screenshots no longer show the Symfony toolbar.

### Fixed

- Schema: the collation of the key columns (`device_id`, `user_identifier`) is now applied on `postGenerateSchema`, once the schema tool has added every foreign key, instead of replacing the `device_trust`, `device_user` and observation tables on `postGenerateSchemaTable` (audit XI). On DBAL 4.5+ `ExactCollationListener` replaced the table with `setClassTable()`, and ORM 3.7–3.8's schema tool then added the foreign keys that start on that table to the replaced object, so on MySQL / MariaDB they were lost from the generated schema, with their indexes (these tables have no foreign key today, so nothing was lost yet; a host association onto them would have been). On DBAL 4.5+ the tables are now rebuilt with their table editor and swapped into a new schema (`GenerateSchemaEventArgs::setSchema()`), which keeps every foreign key and index; on older DBAL (and ORM versions without `setSchema()`) the column options are set in place. Managers that do not map the bundle entities are skipped, the exact collations are unchanged, and `ExactCollationListener::postGenerateSchemaTable()` stays as a deprecated no-op (the constructor's registry argument is unused but kept).

## [1.10.2] - 2026-11-19

### Changed

- Documentation (audit UE2-DI-1, UE2-DI-2): for custom adapters without the atomic port, the `find()` re-read helps only
  if `find()` reads storage and not the ORM identity map or a cache; ORM-based adapters should implement the atomic
  port. A row lock in `find()` can close the window in the analysis only, since the stability recalculation runs
  without a transaction runner. The three extra round trips of the MySQL / MariaDB lock-wait bound are documented
  (audit UE2-DI-4).

### Fixed

- An upsert whose third locking read after a unique violation did find the byte-identical row now does one more
  `UPDATE` pass instead of rethrowing the unique violation (audit UE2-DI-3).

## [1.10.1] - 2026-11-19

### Security

- 1.10.0 fixed advisory [GHSA-crq5-q557-89xf](https://github.com/nowo-tech/DeviceIntelligenceBundle/security/advisories/GHSA-crq5-q557-89xf)
  (Low, `>= 1.1.4, < 1.10.0`): a concurrent device analysis or stability recalculation could revert a device status
  the host had changed, for example `revoked` back to `active`. 1.10.1 narrows the remaining window for custom
  `DeviceRepositoryInterface` adapters that do not implement `AtomicDeviceRepositoryInterface` (audit UE-DI-1): the
  analysis and the recalculation read the device again just before `save()` and keep its stored status, first-seen
  time and metadata. Such adapters must implement `AtomicDeviceRepositoryInterface` to be fully fixed. See UPGRADING
  and CONCURRENCY.md.

### Changed

- MySQL and MariaDB: `doctrine.lock_timeout_ms` (default 5000 ms) now bounds the analysis unit's own transaction there
  too. The session `innodb_lock_wait_timeout` is lowered to the bound (whole seconds, rounded up) for that transaction
  only, and the previous value is set back afterwards, also on failure (audit UE-DI-3). PostgreSQL keeps
  `SET LOCAL lock_timeout`. Inside a host transaction nothing changes.
- Custom device repositories without the atomic port: the analysis and the stability recalculation call `find()` once
  more, just before `save()`.

### Fixed

- An upsert (trust grant, device-user link) whose conflicting row was deleted between the unique violation and the
  locking re-read, for example by a GDPR erasure or a cleanup, threw `LookAlikeIdentifierException` with a misleading
  message. It now tries the `INSERT` again, at most three times in all. Only a row that the folding SQL filter matches
  without a byte-identical key is a look-alike (audit UE-DI-2).
- The stability recalculation, for adapters without the atomic port, no longer stores a device deleted while it ran.
- Tests: the two-connection status test uses `revoked`, a real `DeviceStatus` value, and reads the row back through the
  mapper (audit UE-DI-4).

## [1.10.0] - 2026-11-19

### Added

- **Conflict answer on the collect endpoint (REQ-RUNTIME-003).** A deadlock, lock-wait timeout or serialization
  failure while the observation is stored now answers **409** with `X-Conflict-Code: CONFLICT_001` and `Retry-After`
  (nothing committed, retrying is safe). When something was committed earlier in the same request (transaction-doctrine
  1.6 request watermark) or the commit outcome is unknown, it answers `CONFLICT_002` with no `Retry-After`. The JSON
  body keeps `error` (`"conflict"`) and adds `code`. Unique violations and lost connections are not mapped.
- `conflict_response.status` (409 or 503) and `conflict_response.retry_after`. When unset they follow
  transaction-doctrine's `conflict_response`, else 409 and 1 s.
- `doctrine.lock_timeout_ms` (default 5000): the PostgreSQL `lock_timeout` of the analysis unit's own transaction.
- Optional core ports `AtomicDeviceRepositoryInterface`, `AtomicDeviceUserRepositoryInterface` and
  `AtomicTrustedDeviceRepositoryInterface`, implemented by the Doctrine repositories, for adapters that write without
  a read-modify-write.
- `docs/CONCURRENCY.md`: write paths, conflict codes, host options and residual risks.
- CI: the "Databases" job runs the persistence tests and new two-connection race tests on MySQL 8.4, MariaDB 11.4 and
  PostgreSQL 16, on DBAL 3 and DBAL 4. A "Dependency floor" job runs the suite with the lowest
  `nowo-tech/transaction-*` versions and checks that the demo lock installs them.

### Changed

- Requires `nowo-tech/transaction-contract` ^1.0 and `nowo-tech/transaction-doctrine` ^1.6.
- The analysis stores the device and its observation in one transaction (`DeviceIntelligenceTransactionRunner`: its own
  transaction, or a savepoint inside the host's). The device is now written after the risk assessment, so no GeoIP,
  reputation or rule call runs while its row is locked. `DeviceIntelligence` takes an optional
  `TransactionRunnerInterface` as its last constructor argument.
- A matched device is stored with `observation_count = observation_count + 1`. Its status, first-seen time and
  metadata are not written. The stability recalculation writes the stability column alone.
- A login increments `login_count` in SQL (`DoctrineDeviceUserRepository::recordLogin()`).
- A trust revocation writes `revoked_at` alone. Revoking an expired or already revoked trust keeps its trusted-at time,
  expiry and label (it used to reset them).
- `DoctrineTrustedDeviceRepository::save()` and `DoctrineDeviceUserRepository::save()` upsert without a
  read-modify-write: an `UPDATE` by id or an `INSERT`. A concurrent `INSERT` of the same device and user is updated
  instead of failing with `UniqueConstraintViolationException`.

### Fixed

- **Lost updates under concurrent writes** (contention only):
  - Two logins of the same user on the same device at once counted once, or failed with a unique violation.
  - Two analyses of the same device counted one observation.
  - A revocation that had read the trust before a concurrent change wrote the old expiry and label back.
  - Two first trusts of the same device and user ended in a 500.
- **A device status changed by the host could be reverted.** An analysis or stability recalculation running at the
  same time wrote back the status it had read (for example `revoked` back to `active`). Trusted-device rows were not
  affected.
- A lock conflict on the collect endpoint ended in a 500.
- The DBAL 3 test runs: SQLite's platform is resolved for both DBAL 3 (`SqlitePlatform`) and DBAL 4 (`SQLitePlatform`).

## [1.9.3] - 2026-11-16

### Security

- GDPR erasure and export reach again the rows stored before 1.9.2 for user identifiers that 1.9.2 refuses (an internal NBSP or ZWSP, or edge whitespace that earlier releases trimmed on write). 1.9.2 skipped such users in the default subject resolver and the UserKit eraser's `previous_email`, so an erasure request left their device rows in the database and an export omitted them (audit RE1-L1). New `DeviceIntelligencePersonalDataSubject::forPersonalDataRequest()` matches the raw value and its trimmed variant, each byte for byte; look-alikes are never matched, and normal reads and writes keep the strict rules. See UPGRADING.

### Changed

- docs: demo screenshots regenerated on the UiKit base style
- demo: opt into UiKit 2.51 `nowo_ui_kit.base_stylesheet`, use `nowo-ui-header__nav` for the masthead nav, screenshots regenerated with the themed shell

### Documentation

- Spec Kit baseline (REQ-SPECKIT-003): `specs/001-baseline` maps the 1.7 private entity manager classes, the 1.9 GDPR ports and UserKit bridge, the 1.9.2 exact-collation listener and `DeviceTrustServiceInterface` (227/227 units).

## [1.9.2] - 2026-11-13

### Security

- **Trusted-device rows are no longer resolved across look-alike users (REQ-SEC-006).** `DoctrineTrustedDeviceRepository`
  looked a trust up with SQL alone and `LIMIT 1`. On the default MySQL / MariaDB collations `josé@`, `Jose@` and (PAD
  SPACE) `jose@ ` equal `jose@`, so an attacker who trusted their own device as `josé@` and then logged in there as
  `jose@` passed `isTrusted()` and `#[RequireTrustedDevice]` with their own row; `jose@` trusting or revoking that
  device rewrote the attacker's row. Affects 1.0.0 to 1.9.1 with Doctrine storage on MySQL / MariaDB. Every lookup
  (`findActive`, `save`, `forUser`, and `find` / `forUser` / `forDevice` / `save` of `DoctrineDeviceUserRepository`)
  now reads every row the SQL filter returns, without `LIMIT`, and keeps only the byte-identical one (`===`).
- **A concurrent duplicate login no longer fails the request.** A second simultaneous login of the same user on the
  same device (two tabs, a mobile retry) could race the device-link write; the rethrown unique violation then ended
  `LoginSuccessEvent` with an error after the credentials had been accepted. It is logged
  (`device_intelligence.login_associate_raced`) and the login continues: the link the other request wrote is already
  there. Present since 1.0.0.
- **A write never overwrites a look-alike's row.** When a unique key on a folding collation rejects the new row, the
  repositories throw `Nowo\DeviceIntelligence\Exception\LookAlikeIdentifierException` (fail closed); a login keeps
  working, only that device-user link or trust is not recorded until the `ALTER` of UPGRADING.
- **NO PAD binary collation on `device_id` and `user_identifier`** of `device_trust`, `device_user` and `observation`
  in the generated schema: `utf8mb4_0900_bin` on MySQL 8.0.17+, `utf8mb4_nopad_bin` on MariaDB, `utf8mb4_bin` (PAD
  SPACE) only for MySQL 5.7 / 8.0.0-8.0.16 or a `server_version` without the patch number, with a logged warning.
- **`UserIdentifier` refuses unusable values instead of trimming them**: edge whitespace, control or format characters
  (`\n`, ZWSP, BOM, bidi marks), non-ASCII spaces (NBSP) and invalid UTF-8 throw `InvalidValueException`. The default
  resolver's callers already treat that as "no user" (not trusted, no device link). Legacy device links and trusts
  holding such values are skipped, never mapped onto another user; legacy observations stay readable with an unknown
  user. Device ULIDs are matched with an anchored `/D` pattern.

## [1.9.1] - 2026-11-13

### Fixed

- DoctrineBundle 3.4: bundle entities resolved to the default manager when a dedicated manager is configured.
  DoctrineBundle 3.4 `auto_discover_entities` (on by default for a manager without `mappings` and `auto_mapping`, with
  Symfony 7.3+ and doctrine/orm 3.6+) gives that manager's metadata driver chain a default attribute driver, which
  claims every `#[ORM\Entity]` class. With `doctrine.entity_manager` set, `getManagerForClass()` then answered the
  host's default manager, so the bundle repositories (and host-facing code such as `getRepository()`, EntityType or
  MapEntity) used its database ("no such table: device_intelligence_device"). `DeviceIntelligenceEntityManagerPass`
  now also registers a driver that maps nothing for the bundle entity namespace, first in the chain of every other
  manager (of every manager with `doctrine.enabled: false`): `Doctrine\ExcludedEntityNamespaceDriver`, only the public
  `addDriver()` of the `doctrine.orm.<manager>_metadata_driver` chain. The entities resolve to the configured manager
  on DoctrineBundle 2.x, 3.3 and 3.4+. Nothing changes without the option. Affected in practice: 1.7.0 to 1.9.0
  (every release with `doctrine.entity_manager`; all allow `doctrine/doctrine-bundle` `^3.0`) with DoctrineBundle 3.4.0
  or later.
### Changed

- Demo uses the UiKit base style and FormKit forms (REQ-DEMO-014/015).

## [1.9.0] - 2026-11-11

### Added

- GDPR personal-data ports: `DeviceIntelligencePersonalDataEraserInterface::erase()` and
  `DeviceIntelligencePersonalDataExporterInterface::export()` on a `DeviceIntelligencePersonalDataSubject` (user
  identifier), with `DeviceIntelligenceErasureResult` counts and `DeviceIntelligencePersonalDataErasedEvent` after the
  commit (not dispatched inside the caller's transaction). Byte-exact matching (a MySQL `*_ci` collation never erases
  or exports a case or accent variant), idempotent, batched by primary key; devices still referenced by someone else
  are kept.
- Optional nowo-tech/user-kit-bundle bridge: eraser `device_intelligence` (priority 150) and export section `devices`
  (priority 35); `personal_data.enabled` (default `true`) and `personal_data.subject_resolver` with the
  `DeviceIntelligencePersonalDataSubjectResolverInterface` SPI.

### Behaviour change

- With nowo-tech/user-kit-bundle installed, upgrading alone makes UserKit's account erasure delete the person's
  observations, device links, trust grants and the devices nobody else references, and adds a `devices` section to the
  export (it clashes with a host provider using the same key, see UPGRADING). Set `personal_data.enabled: false` if
  you must keep these records or already erase / export them yourself.

## [1.8.0] - 2026-11-11

Architecture bar W2 (standard Symfony by default, strict DDD opt-in): rule 8, tests on both host kinds. Audit: Appendix LR, A (no findings).

### Changed

- A host without Doctrine ORM (no DoctrineBundle, or no `doctrine.orm`) that leaves `doctrine.enabled` at `true` now
  gets an explicit build error (configure the ORM, or set `doctrine.enabled: false` and bind the four repository
  ports) instead of the mapping pass's "Could not find the manager name parameter".
- Standard hosts: no configuration change, same defaults.

### Tests

- `tests/Integration/Ddd/HostAdaptersKernelTest.php`: a DDD host without DoctrineBundle, the four repository ports
  bound to host stores, the analysis through a host controller and the trust API; a kept-alive kernel serving 600
  requests for two users through `http_kernel` handle / terminate without the services resetter (devices kept in
  the host stores, never mixed, flat memory).

### Documentation

- USAGE, "Using it from a DDD / hexagonal application": both paths (standard Doctrine host / host adapters without
  Doctrine) with the configuration of each.

## [1.7.0] - 2026-11-11

Device and trust rows are written with DBAL; the host entity manager is never flushed or reset; real `entity_manager` option. See [UPGRADING](https://github.com/nowo-tech/DeviceIntelligenceBundle/blob/v1.7.0/docs/UPGRADING.md#to-170).

### Security

- **The analysis no longer flushes the host's entity manager.** The Doctrine repositories wrote each device,
  observation, device/user and trust row with `persist()` + `flush()` on the manager that maps the entities — the host's
  default manager in a standard host — from request listeners and on login, which also committed whatever the host had
  pending at that moment. They now write only their own row with a DBAL `INSERT` / `UPDATE` built from the class
  metadata (`DbalEntityWriter`), inside a SAVEPOINT when the host has a transaction open (a bundle failure never
  aborts the host transaction; the original error is rethrown).
  With PostgreSQL SEQUENCE ids (DBAL 3, or `identity_generation_preferences: sequence`) the writer generates the id
  through the entity's ORM generator and writes it in the INSERT; with IDENTITY it reads `lastInsertId()` before the
  savepoint is released. Persistence tests run on SQLite, MySQL 8.0 and PostgreSQL 17 (identity and sequence) in CI.

### Added

- **`doctrine.entity_manager`** option: map the entities on a dedicated entity manager only (also when the default
  manager has `auto_mapping: true`); the repositories use it; an unknown manager fails the build. Default `null`.
- **`DeviceTrustServiceInterface`** (trust / revoke / isTrusted), aliased to `DeviceTrustService`.
- **`ConsentGateInterface`** and **`IpReputationProviderInterface`** are now consumed by the analysis (overridable
  aliases; defaults `AllowAllConsentGate` / `NullIpReputationProvider`): the gate decides per collector after the
  privacy processor; the reputation reaches the new **`ip_reputation`** risk rule (Tor / proxy / VPN / hosting and the
  provider's risk; scores 0 without a provider, so default results are unchanged).
- Kernel tests: standard host (host changes stay pending), host transaction, dedicated manager with `auto_mapping`,
  `doctrine.enabled: false` with `auto_mapping`, no DoctrineBundle at all, unknown manager. CI runs the persistence
  tests on MySQL and PostgreSQL (with a SEQUENCE job) and the Igor worker audit.

### Changed

- **`doctrine.enabled: false`** now maps the entities nowhere (also not through `auto_mapping`); before, the mapping
  was registered anyway.

### Deprecated

- The hard requirement on `doctrine/orm` / `doctrine/doctrine-bundle`: they move to `suggest` in 2.0 (the core and the
  bundle already run without them with `doctrine.enabled: false`).

## [1.6.0] - 2026-11-10

Target 1.6.0. See [UPGRADING](UPGRADING.md#to-160).

### Added

- The core ports `DeviceIntelligence` uses are service aliases a host can override one by one:
  `GeoIpProviderInterface`, `MetricsRecorderInterface`, `DeviceLabelerInterface`, `NetworkSignalProviderInterface`,
  `ServerSignalProviderInterface`, `PrivacyProcessorInterface` and `DeviceCandidateProviderInterface`. The defaults
  are the implementations `DeviceIntelligence::create()` uses, so the analysis is unchanged until an alias is
  overridden (before, plugging a GeoIP or metrics adapter meant redefining the whole `DeviceIntelligence` service).
- USAGE: rules for these adapters (audit KK): decorate the privacy processor and call the default first (a
  replacement takes over consent, `strict` mode and `store_user_agent` filtering of the stored signals), GeoIP and
  network adapters see the raw IP and must not return or log it, adapters with caches implement `ResetInterface`.
  UPGRADING notes that an existing host alias of one of these interfaces now takes effect.

### Documentation

- USAGE: new section "Using it from a DDD / hexagonal application" (where the bundle sits in a hexagonal host, the ports, events and DTOs to build an anti-corruption layer on, and the known limitations).

## [1.5.2] - 2026-10-31

PHP 8.3 hosts can warm Doctrine proxies (entities no longer final); assets/translator prepend only when the components are installed. See [UPGRADING](UPGRADING.md#to-152).

### Fixed

- **PHP 8.3 hosts can warm their cache again.** The Doctrine entities (`DeviceEntity`, `DeviceObservationEntity`,
  `DeviceTrustEntity`, `DeviceUserEntity`) were `final`; on PHP 8.3 (no native lazy objects) Doctrine builds its
  proxies by extending the entity, so `cache:warmup` / kernel boot failed with `Cannot generate lazy ghost: class
  "Nowo\DeviceIntelligenceBundle\Entity\DeviceEntity" is final`. The entities are no longer `final`. See
  [UPGRADING](UPGRADING.md#unreleased).
- **Hosts without `symfony/asset` or `symfony/translation` boot.** The extension always prepended
  `framework.assets` and `framework.translator`, which enables those features: without the components the container
  failed to compile (Symfony <= 8.1: `Asset support cannot be enabled as the Asset component is not installed`;
  Symfony 8.2: `The "framework.assets" configuration is handled by the "asset" extension, which is not registered`).
  The asset package and the translation path are now only prepended when the matching component is installed.

### Changed

- Rector: `ExplicitNullableObjectInstanceRector` skipped (keeps the `null === $x` style; `rector-dry` is clean again),
  deprecated skipped rules removed; `rector/rector` dev constraint `^2.8`.

### Tests

- `HostAppKernelTest`: a host kernel with the default Doctrine configuration boots, warms its cache and creates
  proxies of every bundle entity (PHP 8.3 and 8.4+). The test kernel no longer removes
  `doctrine.orm.proxy_cache_warmer`. CI also runs the host kernel tests after removing `symfony/asset` and
  `symfony/translation`.

## [1.5.1] - 2026-10-30

Bundle routes are opt-in again: controllers are no longer autoconfigured, so a host's routing.controllers import does not expose them (REQ-SF-004 rule 7). See [UPGRADING](UPGRADING.md#to-151).

### Security

- **The collect endpoint is opt-in again (REQ-SF-004 rule 7).** On Symfony >= 7.3 the `#[AsController]` /
  `#[Route]` autoconfiguration tagged `CollectController` with `routing.controller`, so a host's default
  `config/routes.yaml` (`controllers: { resource: routing.controllers }`) exposed the public
  `POST /_device/collect` endpoint even when the host did not import the bundle `routes.php`. The controller is no
  longer autoconfigured (`#[AsController]` removed, explicit `controller.service_arguments` tag).

## [1.5.0] - 2026-10-28

DeviceIntelligence: template overrides always win (TwigPathsPass); Twig Extra documented. See [UPGRADING](UPGRADING.md#to-150).

### Changed

- **Twig template overrides win (REQ-TWIG-001).** The views were registered by prepending `twig.paths`, so an application
  copy under `templates/bundles/NowoDeviceIntelligenceBundle/` was not guaranteed to take precedence. `TwigPathsPass`
  now prepends that directory (when it exists) and then adds the bundle views on the native Twig loader.

### Documentation

- INSTALLATION / README: Twig Extra requirement (REQ-TWIG-004); overridable templates table, procedure and freeze rule.

## [1.4.1] - 2026-10-25

Reproducible JS bundle: the build stamp is a hash of the sources instead of the build time. See [UPGRADING](UPGRADING.md#to-141).

### Changed

- **Developer tooling (REQ-CS-001):** `make validate-phpdoc` is read-only: it fails when a class or public method in `src/` has no PHPDoc block (the former script added `@return` tags that `cs-fix` (`no_superfluous_phpdoc_tags`) removes again).
- **Assets:** reproducible bundle build. `__DEVICE_INTELLIGENCE_BUILD_TIME__` now holds a build id (a hash of the TypeScript/CSS sources and `pnpm-lock.yaml`) instead of the build time, so rebuilding unchanged sources (`make assets`, `make release-check`) no longer rewrites the committed JS; the console line reads `script loaded, build: <id>`.
- Developer tooling: `pnpm run lint` passes again (no useless initial assignment in the timezone collector).

## [1.4.0] - 2026-10-21

Wave-2 REQ compliance: documented public API, injectable clock, enforced coverage gate with lib/ instrumented, PHP translation parity. See [UPGRADING](UPGRADING.md#to-140).

### Changed

- PHPDoc description on every public method of `src/` (226 added); global classes imported instead of leading-backslash names (`global_namespace_import`) (REQ-CS-001).
- `DeviceMapper`, `CleanupCommand` and `RiskTestCommand` take the PSR-20 clock (autowired, `SystemClock` by default) instead of `new DateTimeImmutable()` (REQ-DI-001).
- Rector targets PHP 8.3 (the package floor) with at most 2 workers; pending Rector findings applied (REQ-CS-003, REQ-SF-001).
- `composer coverage-check` and the CI coverage job fail below 99% lines; CI instruments `lib/` as well (`pcov.directory=.`) so the reported value matches local runs (REQ-TEST-003/007).
- Translation key parity is checked by `.scripts/validate-translations.php` (PHP) instead of the former Python helper (REQ-MAKE-004, REQ-TEST-004).
- `release-check` starts with `ensure-up` and runs `check-no-raw-html-form` (REQ-MAKE-002).
- `composer.json`: `extra.nowo-package-version` / `branch-alias` aligned with 1.4, vanity keyword `nowo` removed (REQ-PKG-004).
- `docs/DEMO-FRANKENPHP.md`: Symfony 8 demo PHP-version policy and smoke check (REQ-DEMO-010, REQ-TEST-011).
- `.scripts/check-no-cursor-coauthor.sh` accepts git worktrees (REQ-GIT-001).

## [1.3.3] - 2026-10-21

Dependency refresh (bundle and demos). See [UPGRADING](UPGRADING.md#to-133).

### Changed

- Dependencies refreshed (Composer and pnpm/npm lock files of the bundle and its demos, campaign 2026-10-19); shipped assets rebuilt.
- Documentation: wave-1 REQ compliance — README documentation list order and canonical FrankenPHP claim line, CONTRIBUTING Code of Conduct link and post-release-commit trailer check, canonical issue templates, doc TOCs, demo `.env.example` comments, Spec Kit inventory exclusion note.

## [1.3.2] - 2026-10-15

### Changed

- **Batched observation purge.** `DoctrineObservationRepository::deleteOlderThan(\DateTimeImmutable $cutoff, int $batchSize = 1000)` deletes in keyset batches (`id > :last ORDER BY id LIMIT n`, one short `DELETE … WHERE id IN (…)` per batch) instead of one unbounded `DELETE`, so `nowo:device-intelligence:cleanup` and the Messenger cleanup handler no longer lock the table on large instances (Nowo REQ-MESSENGER-007). Same result and return value.

## [1.3.1] - 2026-10-13

### Changed

- **Dev:** Vitest 4 (`vitest` / `@vitest/coverage-v8` `^4.1.11`); TS test thresholds unchanged.

## [1.3.0] - 2026-10-13

### Security

- `DeviceRequestSubscriber` now runs at `kernel.request` priority **7**, strictly after the Security firewall (priority 8). With the same priority the firewall could run later, so the security token was not yet available and `DeviceContext::trusted` was always `false` for authenticated users.
- Collect endpoint: the `nonce` is now validated (`^[A-Za-z0-9_-]{8,128}$`). Unbounded or `|`-containing nonces could break the signed observation token format.

### Fixed

- Collect endpoint returns **429 Too Many Requests** (instead of 403) when the `collect` rate limit is exceeded.
- Rate-limit `interval` values are parsed like Symfony RateLimiter (relative strings such as `1 day`/`2 weeks`, or ISO 8601 durations such as `PT15M`). Unsupported values used to silently fall back to 60 seconds; they are now rejected at config time (and with `\InvalidArgumentException` for attribute intervals).

### Changed

- Documented `endpoint.allowed_origins` (extra hosts accepted by the `origin` CSRF mode).

## [1.2.2] - 2026-10-13

### Dependencies

- `typescript-eslint` 8.70.1 → 8.71.1 (frontend tooling; #26).
- `jsdom` 30.1.1 → 30.1.2 (frontend tooling; #27).
- `vite` 8.3.2 → 8.3.4 (frontend tooling; #28).

### Changed

- **Shipped asset:** `src/Resources/public/js/device-intelligence.min.js` (and its source map) rebuilt by the Dependabot asset workflow with the refreshed toolchain; no behaviour change.

## [1.2.1] - 2026-10-09

### Fixed

- **DI:** the `kernel.environment` parameter is narrowed to a string before the prod `endpoint.csrf` guard (PHPStan 2.3 `cast.string`); behaviour unchanged.

### Dependencies

- Dependabot: `symfony/security-bundle` 8.1.8; JS dev tooling `vite` 8.3.2, `eslint` 10.11.0, `jsdom` 30.1.1, `@types/node` 26.6.4 (`package.json` / `pnpm-lock.yaml`); rebuilt `device-intelligence.min.js`.
- Composer refresh: `doctrine/orm` 3.7.4, `doctrine/dbal` 4.5.0, Symfony 8.1.8 components, `twig/twig` 3.30.0; dev tooling `phpstan/phpstan` 2.3.1, `rector/rector` 2.7.0, `nowo-tech/phpstan-frankenphp` 1.2.3.
- Demo: Symfony 8.1.8, `doctrine/orm` 3.7.4.
- PHP CS Fixer bot pass (import order / Yoda style in the extension).

## [1.2.0] - 2026-09-28

### Security

- `endpoint.csrf: none` is refused in the `prod` environment (compile-time / config validation).

### Changed

- **Doctrine ORM SortDirection:** replace string `'ASC'`/`'DESC'` in `#[ORM\OrderBy]` and QueryBuilder `orderBy`/`addOrderBy` with `SortDirection::Ascending`/`Descending` (doctrine/orm deprecation, https://github.com/doctrine/orm/issues/11313); require `doctrine/orm` `^3.7` where applicable.

## [1.1.5] - 2026-09-27

### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.

### Changed

- **Worker safety (Igor):** justified `// @igor-ignore` annotations and/or `ResetInterface` / request-scoped fixes so `make igor` passes on package `src/`.

[1.2.0]: https://github.com/nowo-tech/DeviceIntelligenceBundle/releases/tag/v1.2.0
[1.1.5]: https://github.com/nowo-tech/DeviceIntelligenceBundle/releases/tag/v1.1.5

## [1.1.4] - 2026-09-24

### Fixed

- **FrankenPHP worker (no kernel reset):** new `RequestStateResetSubscriber` clears the in-memory repositories
  (`doctrine.enabled: false`), the rate limiter cache-failure fallback and the profiler collector at the start of
  every main request (they are also tagged `kernel.reset`).
- **Doctrine:** repositories resolve the entity manager from the registry per call (a manager closed by a failed
  flush is reset on the next call), refresh managed entities on read, and detach after mapping, so trust revocations
  and device status changes made by another worker are seen immediately and the identity map no longer grows with traffic.
- **Velocity:** `CacheVelocityEngine::increment()` drops timestamps older than the 7-day TTL before writing;
  `InMemoryVelocityEngine` exposes `reset()` for hosts that wire it as a shared service.
- See `docs/FRANKENPHP-WORKER-AUDIT.md`.
- **QA:** fixed PHPStan findings (0 errors at level 8): invalid `@return` PHPDoc in `DeviceRateLimiterInterface::consume()`.

### Documentation

- Spec Kit inventory **208/208**; `FR-WORKER-001`; constitution / SECURITY / USE-CASES / README worker notes.

[1.1.4]: https://github.com/nowo-tech/DeviceIntelligenceBundle/releases/tag/v1.1.4

## [1.1.3] - 2026-09-07

### Changed

- **Dependency:** bump `symfony/security-bundle` from 8.1.2 to 8.1.6 (Symfony group).
- **Deps (dev):** eslint, typescript-eslint, `@types/node` bumps.
- **Style:** PHP CS Fixer phpdoc cleanup.

### Notes

- **No API or configuration changes** for integrators.

[1.1.3]: https://github.com/nowo-tech/DeviceIntelligenceBundle/releases/tag/v1.1.3

## [1.1.2] - 2026-09-03

### Fixed

- **CI / frontend:** raise Node.js to **22** (and `engines.node` to `>=22.19.0`) so jsdom 30 / undici 8 TypeScript tests run correctly.

### Changed

- **Deps:** bump `jsdom` to 30.x, `@eslint/js`, `typescript-eslint`; refresh Composer lockfiles.

[1.1.2]: https://github.com/nowo-tech/DeviceIntelligenceBundle/releases/tag/v1.1.2

## [1.1.1] - 2026-08-26

### Changed

- Browser IIFE: import `DeviceIntelligence` as a local binding before re-export so the published bundle evaluates `client.ts` before the logger.

### Notes

- **No API or configuration changes** for integrators unless noted above.

[1.1.1]: https://github.com/nowo-tech/DeviceIntelligenceBundle/releases/tag/v1.1.1

## [1.1.0] - 2026-08-24

### Added

- profiler AJAX bridge, rate limiter tweaks, and collector UI icons.

### Changed

- **Dependencies:** routine Composer/npm bumps (Dependabot).
- **CI:** git hooks and release hygiene (REQ-GIT-001).
- **Docs:** Spec Kit baseline refresh.
- **Style:** PHP CS Fixer alignment.

### Notes

- **No API or configuration changes** for integrators unless noted above.

### Added

- Web Profiler fingerprint icon (`Icon/device-intelligence.svg`) in the toolbar and sidebar.
- Profiler panel on the HTML request is filled from the Ajax `POST /_device/collect` when the client sends `X-Previous-Debug-Token` (`ProfilerAjaxBridgeSubscriber`).
- Browser client sends `X-Previous-Debug-Token` when the Web Debug Toolbar is present so collect can be linked to that page profile.

### Documentation

- Integrator use cases with copy-paste controllers: checkout step-up, new-device login, trusted devices, coupons, custom risk rules (`docs/USE-CASES.md`).
- Symfony 8 demo: eight examples on distinct paths (`/en`, `/en/checkout`, `/en/login`, `/en/trust`, `/en/privileged`, `/en/coupon`, `/en/export`, `/en/alerts`), in-memory login, trust/coupon/export actions, VIP allowlist rule.

### Fixed

- Demo Twig no longer uses raw `<form>` / `<input>` (REQ-TWIG-005): CSRF actions and login use `CsrfOnlyType` / `LoginType` with `form_start` and a `{% for child in form %}` loop.
- Rate-limit cache keys are hashed so they are valid PSR-16 (colons / `@` in device and user ids no longer fail open after each request).
- Demo Doctrine `DATABASE_URL` uses a three-part `serverVersion=8.0.0` so DBAL 4 does not treat MySQL 8 as older than 8.0.0.
- Demo Web Profiler: drop deprecated `framework.profiler.collect_serializer_data` (removed in Symfony 9).
- Empty Device Intelligence panel: cookie/path diagnostics and clearer empty-state copy.
[1.1.0]: https://github.com/nowo-tech/DeviceIntelligenceBundle/releases/tag/v1.1.0

## [1.0.1] - 2026-08-23

Profiler i18n, observation-cookie hydration, Pentatrion Vite demo, Spec Kit inventory, and Nowo checklist alignment.

### Added

- Web Profiler catalogues in domain `NowoDeviceIntelligenceBundle` (`en`, `es`, `it`, `fr`, `pt`, `de`, `nl`) with key-parity check (`make validate-translations`).
- Browser client console logger (`📦 [device-intelligence] script loaded, build time: …`).
- Symfony 8 demo compiles collectors with **Pentatrion Vite** + **pnpm** (`vite_entry_script_tags`). Hosts can still use the published IIFE via `assets:install`.
- Spec Kit baseline inventory **204/204** (`lib/` + `src/`) with semantic `FR-*`; bundle constitution (Device ID is not a credential).
- AI security audit record (REQ-SEC-004): **Pass (conditional)** / Medium in `docs/SECURITY.md`.

### Changed

- Demo FrankenPHP image: `dunglas/frankenphp:1-php8.5-alpine` (`FRANKENPHP_MODE` still in `.env`).
- Data collector tagged `kernel.reset`; translator paths prepended for the profiler domain.
- Messenger: document host-owned retry / `failure_transport`; handlers stay idempotent.
- `composer.json` suggests `symfony/translation` for the profiler panel.

### Fixed

- `DeviceRequestSubscriber` hydrates `_device` from the `di_obs` cookie even when `observe_on_every_request` is `false`, so the Web Profiler panel fills on the next HTML request after collect.
- Demo path repository: require `@dev` instead of `dev-master as 1.0.99`; drop hardcoded package `version` so Composer uses the VCS tag.
- GitHub CI: `composer validate --strict` without a hardcoded version; PHP 8.3–8.5 matrix; Scrutinizer PHP **8.3**.
- Demo Doctrine: drop `auto_generate_proxy_classes` (DoctrineBundle 3.3).
- Demo `make up`: `sleep 5` and Packagist/WSL DNS comment on Compose `dns:`.
- Bug report template grep string (`DeviceIntelligenceBundle`).

### Documentation

- USAGE / INSTALLATION / CONFIGURATION: Pentatrion vs IIFE, profiler cookie hydration, translation overrides, messenger retries.
- Spec Kit manual inventory count **204/204**; SECURITY 12.4.1 + dated AI audit table.

## [1.0.0] - 2026-08-23

First stable release of **Device Intelligence Bundle**.

### Added

- Probabilistic device matching (weighted signals; no monolithic identity hash)
- Named config profiles (`default_profile` + `profiles`) under alias `nowo_device_intelligence`
- Risk engine with explainable rules; Device ID is **not** a credential
- `POST /_device/collect` (`#[Route]` name `nowo_device_intelligence_collect`) with origin checks, nonce replay protection, and rate limits
- Doctrine persistence with configurable table prefix (`device_intelligence_*`)
- Controller attributes: `#[DeviceRisk]`, `#[DeviceRateLimit]`, `#[RequireTrustedDevice]`
- Web Profiler panel `nowo_device_intelligence`
- First-party browser collectors (Vite IIFE `device-intelligence.min.js`) on asset package `nowo_device_intelligence`
- Messenger handlers for observation cleanup and stability recalculation (`ClockInterface`)
- Symfony Flex recipe (`.symfony/recipe/nowo-tech/device-intelligence-bundle/1.0`)
- FrankenPHP Symfony 8 demo (`demo/symfony8`, MySQL, default port **8038**)
- Nowo bundle standards: Docker/Makefile, GitHub workflows, Spec Kit baseline, Engram, Code of Conduct

### Documentation

- README: canonical badges, FrankenPHP banner, `## Documentation`, `## Tests and coverage`
- Integrator docs: INSTALLATION, CONFIGURATION, USAGE, SECURITY, CONTRIBUTING, RELEASE, UPGRADING, GITHUB (REQ-DOCS-018)

[Unreleased]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.11.1...HEAD
[1.11.1]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.11.0...v1.11.1
[1.11.0]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.10.3...v1.11.0
[1.10.3]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.10.2...v1.10.3
[1.10.2]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.10.1...v1.10.2
[1.10.1]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.10.0...v1.10.1
[1.10.0]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.9.3...v1.10.0
[1.9.3]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.9.2...v1.9.3
[1.9.2]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.9.1...v1.9.2
[1.9.1]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.9.0...v1.9.1
[1.9.0]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.8.0...v1.9.0
[1.8.0]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.7.0...v1.8.0
[1.7.0]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.6.0...v1.7.0
[1.6.0]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.5.2...v1.6.0
[1.5.2]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.5.1...v1.5.2
[1.5.1]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.5.0...v1.5.1
[1.5.0]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.4.1...v1.5.0
[1.4.1]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.3.3...v1.4.0
[1.3.3]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.3.2...v1.3.3
[1.3.2]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.3.1...v1.3.2
[1.3.1]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.3.0...v1.3.1
[1.3.0]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.2.2...v1.3.0
[1.2.2]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.2.1...v1.2.2
[1.2.1]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.2.0...v1.2.1
[1.1.4]: https://github.com/nowo-tech/DeviceIntelligenceBundle/releases/tag/v1.1.4
[1.0.1]: https://github.com/nowo-tech/DeviceIntelligenceBundle/releases/tag/v1.0.1
[1.0.0]: https://github.com/nowo-tech/DeviceIntelligenceBundle/releases/tag/v1.0.0
