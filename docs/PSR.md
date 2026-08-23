# PHP-FIG PSR evaluation (REQ-CS-007)

Package: `nowo-tech/device-intelligence-bundle` (`symfony-bundle`)

This document records which [PHP-FIG PSRs](https://www.php-fig.org/psr/) apply to this package.
Only contracts that add clear interoperability or maintainability value are **Adopted**.
Others are **N/A** (or already covered by Symfony) so the decision stays auditable.

## Baseline (always)

| PSR | Decision | How |
| --- | -------- | --- |
| PSR-12 (coding style) | **Adopted** | `@PSR12` in `.php-cs-fixer.dist.php` (Nowo REQ-CS-001). |
| PSR-4 (autoloading) | **Adopted** | `composer.json` `autoload` / `autoload-dev` PSR-4 map for `lib/`, `src/`, and tests. |

## Interface / contract PSRs

| PSR | Decision | Notes |
| --- | -------- | ----- |
| PSR-3 Logger | **Adopted** | `CollectController` type-hints `Psr\Log\LoggerInterface`; tagged `monolog.logger` channel `device_intelligence`. Logs hashed ids only. |
| PSR-6 Cache | **N/A** | Symfony cache adapters wrap the pool; the bundle talks PSR-16. |
| PSR-16 Simple Cache | **Adopted** | Nonce replay and rate-limit buckets via `Psr\SimpleCache\CacheInterface` (`Psr16Cache` over `cache.app`). |
| PSR-7 / PSR-17 HTTP messages | **N/A** | Symfony HttpFoundation is the correct surface. |
| PSR-18 HTTP client | **N/A** | No outbound HTTP client. |
| PSR-11 Container | **N/A** | Constructor injection only; no service locator in the public API. |
| PSR-14 Event dispatcher | **Already satisfied via Symfony** | `AnalyzeService` dispatches Symfony contracts events. |
| PSR-15 HTTP middleware | **N/A** | Kernel subscribers and `#[Route]` controllers. |
| PSR-20 Clock | **Adopted** | `CleanupHandler` and token issuer type-hint `Psr\Clock\ClockInterface`; `SystemClock` when the host has no clock. |

## Summary

- **Adopted beyond baseline:** PSR-3 Logger, PSR-16 Simple Cache, PSR-20 Clock
- **Rule:** do not add `psr/*` Composer dependencies without matching type-hints and DI wiring.
- **Re-evaluate** when the package gains an HTTP client or a public PSR-7 API.

---

_REQ-CS-007 evaluation date: 2026-08-23._
