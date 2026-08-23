# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.0.0] - 2026-08-23](#100---2026-08-23)

## [Unreleased]

### Fixed

- Demo path repository: require `@dev` instead of `dev-master as 1.0.99`, and drop the hardcoded `version` field from the bundle `composer.json` so Composer uses the VCS tag (Packagist) and local path installs resolve.
- GitHub CI: `composer validate --strict` no longer fails on a hardcoded package version; the test matrix uses a full `composer update`; code-style and coverage install from the lockfile on PHP 8.4.
- PHP floor is **8.3** (`@PHP83Migration` / typed class constants). CI tests PHP 8.3–8.5.

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

[Unreleased]: https://github.com/nowo-tech/DeviceIntelligenceBundle/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/nowo-tech/DeviceIntelligenceBundle/releases/tag/v1.0.0
