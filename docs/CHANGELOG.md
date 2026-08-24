# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.0.1] - 2026-08-23](#101---2026-08-23)
- [[1.0.0] - 2026-08-23](#100---2026-08-23)

## [Unreleased]


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

[Unreleased]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/nowo-tech/DeviceIntelligenceBundle/releases/tag/v1.0.1
[1.0.0]: https://github.com/nowo-tech/DeviceIntelligenceBundle/releases/tag/v1.0.0
