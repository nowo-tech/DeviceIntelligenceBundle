# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/device-intelligence-bundle` (`symfony-bundle`) |
| Audited revision | `v1.1.4` |
| Audit date | 2026-09-24 |
| Method | Manual review of every PHP file under `src/` (services, subscribers, controller, Doctrine repositories, Messenger handlers, commands, DI extension, `Resources/config/services.php`) and the framework-agnostic core under `lib/` (autoloaded as `Nowo\DeviceIntelligence\`) |
| Remediation (2026-09-23/24) | W-01/W-03/W-04 per-request state cleared by the bundle-owned `RequestStateResetSubscriber` (main request, priority 4096) and tagged `kernel.reset`; W-02 Doctrine repositories resolve the manager per call (reset when closed), refresh managed entities on read (`HINT_REFRESH` / `refresh()`), and detach after mapping; W-05 velocity payload pruned on write; `InMemoryVelocityEngine::reset()` for optional shared wiring. Regression tests simulate consecutive requests / two workers / host-held managed entities without `reset()` |
| **Verdict** | ✅ **Viable under scenario B** — no bundle-owned state survives a main request; Doctrine reads are always fresh. Clearing the host application's own EntityManager remains the host's responsibility |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | Request path services hold only config and collaborators. The `InMemory*Repository` arrays (W-01) and `SymfonyDeviceRateLimiter::$memory` (W-03) are cleared at the start of every main request |
| Static properties / `static` locals | ✅ | Only pure static factories (`DeviceIntelligence::create()`, `RiskEngineFactory::create()`, `Ulid::generate()`, `TimeWindow::parse()`, ...); no static properties or `static` locals |
| `ResetInterface` / `kernel.reset` coverage | ✅ | Collector, rate limiter and in-memory repositories are tagged `kernel.reset` (scenario A) and `nowo_device_intelligence.request_reset` (scenario B, reset by `RequestStateResetSubscriber`) |
| Request / user / locale captured in services | ✅ | `RequestStack`, `TokenStorageInterface` and `ClockInterface` are read per call; `DeviceContext` lives in the `_device` request attribute, never in a service |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None used; config is compiled into `DeviceIntelligenceConfig` at container build time |
| Doctrine / EntityManager | ✅ | Repositories get the manager from `ManagerRegistry` per call (closed manager reset) and detach entities after mapping; no stale trust rows, no identity-map growth (W-02) |
| Output, headers, `exit`, shutdown functions | ✅ | None. The observation cookie is set through `Response::headers->setCookie()` (`src/Controller/CollectController.php:112`) |
| Resources (files, sockets, cURL) held open | ✅ | None opened by the bundle; only the Doctrine connection and the configured cache pool |
| Memory growth across requests | ✅ | In-memory stores and the fallback map are request-scoped; Doctrine entities are detached; velocity payloads are pruned (W-05) |
| Blocking I/O and timeouts | ✅ | No HTTP, DNS, `Process` or `sleep`. Only DB and PSR-16 cache calls, whose timeouts belong to the DBAL/cache configuration |
| Third-party static state | ✅ | Only Symfony DI/Config at compile time, Symfony Cache (`Psr16Cache`) and Doctrine ORM at runtime |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` included in `phpstan.neon.dist` |

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `Controller\CollectController` | yes (public) | none (collaborators only) | ✅ | ✅ |
| `Event\AnalyzeService` | no (`share(false)`), but held by the shared controller and `RiskTestCommand` | none | ✅ | ✅ |
| `Nowo\DeviceIntelligence\DeviceIntelligence` (core engine) | no (`setShared(false)`), held by `AnalyzeService` | none; collaborators are stateless or the repositories below | ✅ | ✅ |
| `Nowo\DeviceIntelligence\Device\DeviceManager` | no, held by shared subscribers/commands | none | ✅ | ✅ |
| `WeightedDeviceMatcher`, `MatchingConfig`, `RiskEngine` (+ 12 default rules), `RiskLevels`, `ThresholdRiskDecision`, `PrivacyContext` | yes | none (constructor config only) | ✅ | ✅ |
| `Http\AnalysisInputFactory`, `Http\ObservationTokenIssuer`, `Http\OriginValidator`, `Http\CollectRequestValidator` | yes | none; clock, token storage and request read per call; nonce replay stored in the PSR-16 cache | ✅ | ✅ |
| `EventSubscriber\DeviceRequestSubscriber`, `SecurityDeviceSubscriber`, `ControllerAttributeSubscriber`, `ProfilerAjaxBridgeSubscriber`, `AnalyzeSubscriber` | yes | none of their own (`AnalyzeSubscriber` writes into the data collector) | ✅ | ✅ |
| `Request\DeviceContextValueResolver`, `Request\TokenDeviceContextFactory`, `User\SecurityUserIdentifierResolver`, `Trust\DeviceTrustService`, `Infrastructure\SystemClock`, `Config\DeviceIntelligenceConfig` | yes | none | ✅ | ✅ |
| `Nowo\DeviceIntelligence\Velocity\CacheVelocityEngine` (alias of `VelocityEngineInterface`) | yes | none in memory; counters in the cache pool | ✅ | ✅ |
| `RateLimiter\SymfonyDeviceRateLimiter` | yes, `kernel.reset` + request reset | `$memory` fallback map, request-scoped | ✅ | ✅ |
| `Profiler\DeviceIntelligenceDataCollector` | yes, `kernel.reset` + request reset | `$data`, cleared per main request | ✅ | ✅ |
| `Doctrine\Doctrine*Repository` (4), `Doctrine\DeviceMapper`, `Doctrine\TablePrefixSubscriber` | yes | none; manager resolved per call, entities detached after use | ✅ | ✅ |
| `Nowo\DeviceIntelligence\Infrastructure\InMemory*Repository` (4), only when `doctrine.enabled: false` | yes (`setShared(true)`), `kernel.reset` + request reset | `$devices` / `$rows` arrays, cleared per main request | ✅ | ✅ |
| `EventSubscriber\RequestStateResetSubscriber` | yes | none (tagged iterator) | ✅ | ✅ |
| `Messenger\CleanupHandler`, `Messenger\RecalculateStabilityHandler`, 6 console commands | yes | none | ✅ | ✅ |

Value objects (`Analysis`, `AnalysisInput`, `Device`, `DeviceObservation`, `SignalBag`, `RiskAssessment`, `DeviceContext`, events) are created per call and never stored in a service. Doctrine entities are only held inside the EntityManager, never in bundle service properties. `InMemoryVelocityEngine` is registered non-shared (`NowoDeviceIntelligenceExtension.php:243`) but is not aliased or injected anywhere in the bundle wiring.

## Findings

### W-01 — In-memory repositories keep all data for the worker lifetime (Medium)

- **Where:** `src/DependencyInjection/NowoDeviceIntelligenceExtension.php:288-296` registers `InMemoryDeviceRepository`, `InMemoryObservationRepository`, `InMemoryDeviceUserRepository` and `InMemoryTrustedDeviceRepository` as shared services when `doctrine.enabled` is `false`. Each one appends to a private array that is never cleared: `lib/Infrastructure/InMemoryDeviceRepository.php:15,24`, `lib/Infrastructure/InMemoryObservationRepository.php:15,19`, `lib/Infrastructure/InMemoryDeviceUserRepository.php:15,19`, `lib/Infrastructure/InMemoryTrustedDeviceRepository.php:15,19`. None implements `ResetInterface`.
- **Worker impact:** under PHP-FPM this mode forgets everything at the end of each request. In a worker, every device fingerprint, observation (with signals, IP hash, session id and user identifier), device-user relation and trust grant from every visitor stays in memory until the worker restarts. Memory grows without bound, `findCandidates()` and `latestForDevice()` become linear scans over all past traffic, and each worker thread holds a different partial dataset (a trust granted in one thread is invisible to another). Risk rules such as multiple-accounts and velocity then see other users' data from the same thread, which changes decisions compared with classic mode. The default is `doctrine.enabled: true` (`src/DependencyInjection/Configuration.php:354`), so this only affects hosts that opt out of Doctrine.
- **Recommendation:** do not use `doctrine.enabled: false` in worker mode. If an in-memory mode is needed, make the repositories implement `ResetInterface` (clear the arrays) and document it as test-only, or register them non-shared.
- **Status:** Resolved — each `lib/Infrastructure/InMemory*Repository.php` has a `reset()` method; `src/DependencyInjection/NowoDeviceIntelligenceExtension.php` tags them `kernel.reset` and `nowo_device_intelligence.request_reset`, and `src/EventSubscriber/RequestStateResetSubscriber.php` calls `reset()` on every main request (never on sub-requests). The store is now request-scoped, as under PHP-FPM, so risk decisions no longer depend on other visitors served by the same thread. Test: `tests/Unit/EventSubscriber/RequestStateResetSubscriberTest.php`.

### W-02 — Doctrine repositories depend on the EntityManager reset (Medium)

- **Where:** `src/Doctrine/DoctrineTrustedDeviceRepository.php:28-48,83-97`, `src/Doctrine/DoctrineDeviceUserRepository.php:28-34,81-95`, `src/Doctrine/DoctrineDeviceRepository.php:28-44`, `src/Doctrine/DoctrineObservationRepository.php:28-47`. Every `save()` calls `$this->em->flush()`.
- **Worker impact:** under **A**, DoctrineBundle's resetter clears (or recreates after a failure) the EntityManager between requests, so this is safe. Under **B**, entities loaded in earlier requests stay managed; `em->find()` and DQL `getOneOrNullResult()` return the already-managed instance without refreshing its fields. A trust revoked or expired by another worker thread can therefore still look active in this thread (`findActive()` → `ControllerAttributeSubscriber` `#[RequireTrustedDevice]` at `src/EventSubscriber/ControllerAttributeSubscriber.php:49`). The identity map also grows with every device touched. A flush failure (for example a race on the unique constraints `uniq_di_device_user` / `uniq_di_device_trust`, `src/Entity/DeviceUserEntity.php:18`, `src/Entity/DeviceTrustEntity.php:18`) closes the EntityManager, and under B every later request that analyzes a device fails.
- **Recommendation:** keep `services_resetter` enabled (scenario A). If a host disables it, it must call `ManagerRegistry::resetManager()` / `EntityManager::clear()` between requests itself.
- **Status:** Resolved — the four repositories now receive the `doctrine` registry (`src/Resources/config/services.php`; an `EntityManagerInterface` is still accepted for BC) and use `src/Doctrine/ResolvesEntityManagerTrait.php`: the manager is looked up per call and reset through the registry when a previous flush closed it; reads refresh managed entities (`Query::HINT_REFRESH` / `EntityManager::refresh()`) and every entity is detached once mapped to a core value object or flushed. Reads (`findActive()`, `forUser()`, `find()`, `findCandidates()`, relations) therefore always hit the database, so a trust revoked by another worker is not honoured, and the identity map does not grow. The bundle never calls `clear()` on the host's manager; clearing the host application's own entities between requests remains the application's responsibility under scenario B. Tests: `tests/Unit/Doctrine/DoctrineRepositoriesWorkerTest.php` (two entity managers on one SQLite file acting as two workers; host-held managed entity refreshed on read).

### W-03 — Rate limiter fallback map is never pruned or reset (Low)

- **Where:** `src/RateLimiter/SymfonyDeviceRateLimiter.php:20` (`$memory`), written at `:73` and read at `:59`, only inside the `catch (\Throwable)` branches of cache `get()` / `set()`.
- **Worker impact:** when the configured cache pool throws (for example Redis down), counters move into this per-worker array. A bucket key is added per distinct IP hash / user / device and is never removed, so memory grows for as long as the cache keeps failing, and limits are enforced per worker thread instead of globally. Under normal operation the array stays empty. Rated Low because it only occurs on the failure path.
- **Recommendation:** implement `ResetInterface` to clear `$memory`, or cap its size; alternatively fail open/closed explicitly instead of keeping state in the service.
- **Status:** Resolved — `src/RateLimiter/SymfonyDeviceRateLimiter.php` implements `ResetInterface`; it is tagged `kernel.reset` and reset per main request, so the fallback is request-scoped (same as PHP-FPM) and bounded. Test in `RequestStateResetSubscriberTest`.

### W-04 — Profiler collector keeps the previous request's analysis without reset (Low)

- **Where:** `src/Profiler/DeviceIntelligenceDataCollector.php:34` (`$this->data['has_context'] ??= false`) and `:50-70`; fed by `src/EventSubscriber/AnalyzeSubscriber.php:30-33`. The service is tagged `kernel.reset` (`src/Resources/config/services.php:90-91`) and `reset()` clears `$data` (`:78-81`).
- **Worker impact:** under **A** this is correct. Under **B**, a request without a `_device` context keeps the previous request's `device_id`, signals and risk in the profiler panel. The collector is also registered in production (whenever `profiler: true`, the default), where `AnalyzeSubscriber` overwrites `$data` on every analysis; the array is replaced, not appended, so memory is bounded. This only affects developer tooling.
- **Recommendation:** in `collect()`, reset `$data` when no context is present, or set `profiler: false` in production.
- **Status:** Resolved — the collector is also reset by `RequestStateResetSubscriber` at the start of each main request (analyses recorded during the request through `AnalyzeSubscriber` are kept). Test in `RequestStateResetSubscriberTest`.

### W-05 — Velocity counters in the cache are append-only (Low)

- **Where:** `lib/Velocity/CacheVelocityEngine.php:18-30` appends a timestamp per increment and rewrites the item with a 7-day TTL; old timestamps are only filtered on read (`:32-48`).
- **Worker impact:** not worker-specific and not held in PHP memory, but for an active device the cache item keeps growing while it is written at least once every 7 days, so each analysis deserializes a larger array. It does not leak data between requests.
- **Recommendation:** drop timestamps older than the largest window before `set()`.
- **Status:** Resolved — `lib/Velocity/CacheVelocityEngine.php` drops timestamps older than the 7-day TTL (the largest meaningful window, since the item expires then) before `set()`. Test: `tests/Core/Unit/CacheVelocityEngineTest.php`.

No other findings. Request data is always passed as method arguments or stored in the `_device` request attribute; user and clock are read per call; the observation cookie goes through the Response object.

## Usage recommendations in worker mode

- Keep `doctrine.enabled: true` (the default). The in-memory repositories are request-scoped and only suitable for tests/demos.
- Keeping Symfony's `services_resetter` active is still recommended for the host's own services; the bundle no longer depends on it.
- Use a shared cache pool (`cache.pool`, default `cache.app`) backed by Redis/Memcached or a filesystem shared by all workers, so nonce replay protection, velocity and rate limits are global rather than per worker.
- Set `profiler: false` in production.
- Custom risk rules (`#[AsDeviceRiskRule]` / `RiskRuleInterface`), custom `UserIdentifierResolverInterface` or repository replacements must stay stateless, or implement `ResetInterface`.
- Worker demo: `demo/symfony8/docker/frankenphp/Caddyfile` runs the demo in worker mode (`worker { file /app/public/index.php ... }`).

## Re-audit triggers

Re-run this audit when a change adds: properties to request-path services (collect controller, subscribers, `DeviceIntelligence`, `DeviceManager`), a cache or memoization in the core, new in-memory adapters, new `kernel.reset` services, a GeoIP / IP reputation / network provider that does HTTP or DNS, or any use of `$_SERVER` / `$_ENV` at runtime.
