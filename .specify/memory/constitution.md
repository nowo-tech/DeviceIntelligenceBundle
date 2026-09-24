# DeviceIntelligenceBundle Constitution

## Core Principles

### I. Documented integrator contract
Product behavior lives in `specs/001-baseline/spec.md`, `docs/SPEC-DRIVEN-DEVELOPMENT.md`, and integrator docs (`USAGE.md`, `CONFIGURATION.md`, `SECURITY.md`). Demos are illustrative unless promoted in the spec.

### II. Spec-first, test-proven
PHPUnit, PHPStan, and Vitest are the mechanical proof. Behavioral changes require tests.

### III. 100% code inventory traceability
Every production unit under `lib/` and `src/` (PHP, Twig, SVG icons, translation YAML, TypeScript sources) must appear in `specs/001-baseline/code-inventory.md`. Vite IIFE outputs are documented as `FR-BUILD-001`, not extra inventory rows. New files require spec updates in the same PR.

### IV. Device ID is not a credential
A Device ID identifies a probable browser cluster. It is never an authentication factor, session secret, or MFA replacement. Client signals are attacker-controlled by design.

### V. Privacy defaults
Default `privacy.hash_ip: true`. Raw IP is not stored. Logs and the profiler never include full signal bags.

### VI. Cursor + Spec Kit
GitHub Spec Kit is initialized with **Cursor Agent** (`cursor-agent`). Skills live in `.cursor/skills/speckit-*`.

### VII. Symfony compatibility
Follow declared PHP/Symfony ranges in `composer.json` and README badges. FrankenPHP worker (`reset_kernel: false`): bundle-owned per-request state is cleared on every main request; Doctrine reads are always fresh. See `docs/FRANKENPHP-WORKER-AUDIT.md`.

## Governance
Amendments update this file, baseline spec when principles affect behavior, and `CHANGELOG.md` when consumer-visible.

**Version**: 1.1.1 | **Ratified**: 2026-07-07 | **Last Amended**: 2026-09-24
