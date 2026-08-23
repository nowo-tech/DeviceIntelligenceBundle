# Spec-driven development

In this repository, **spec-driven development** has three layers that stay in sync:

1. **GitHub Spec Kit baseline** — [`specs/001-baseline/`](../specs/001-baseline/) ([`spec.md`](../specs/001-baseline/spec.md), [`code-inventory.md`](../specs/001-baseline/code-inventory.md)), initialized with [GitHub Spec Kit](https://github.com/github/spec-kit) (`.specify/`, **Cursor Agent** skills in `.cursor/skills/speckit-*`). The inventory maps **100%** of production code in `lib/` and `src/`. **How to install, initialize, and use Spec Kit:** [`SPEC-KIT.md`](SPEC-KIT.md).
2. **Product behavior** — what **DeviceIntelligenceBundle** guarantees to applications that integrate it (see [`USAGE.md`](USAGE.md), [`CONFIGURATION.md`](CONFIGURATION.md), [`INSTALLATION.md`](INSTALLATION.md)). **PHPUnit** and **PHPStan** (and **Vitest** when applicable) enforce contracts in CI where applicable.
3. **Traceability anchors** — stable **`REQ-*`** identifiers in Makefiles and demos (when present) so changes to scripts, ports, and demo workflows stay discoverable from issues and PRs.

There is no separate executable spec language (for example Gherkin); Spec Kit specs, tests, and static analysis are the mechanical proof alongside this document.

---

## Table of contents

- [User stories](#user-stories)
- [Bundle functional scope](#bundle-functional-scope)
- [Validating the functional spec](#validating-the-functional-spec)
- [Requirement identifiers (`REQ-*`)](#requirement-identifiers-req-)
- [Suggested workflow for contributors](#suggested-workflow-for-contributors)
- [Relationship to Engram / external checklists](#relationship-to-engram-external-checklists)
- [GitHub Spec Kit (summary)](#github-spec-kit-summary)
- [See also](#see-also)

## User stories

| ID | Story |
| --- | --- |
| US-01 | **As a** Symfony integrator, **I want** a collect endpoint and weighted matcher **so that** I can identify probable returning devices without treating Device ID as a login. |
| US-02 | **As an** integrator, **I want** risk scores and controller attributes **so that** I can step-up or block high-risk actions. |
| US-03 | **As an** integrator, **I want** named YAML profiles **so that** matching weights and privacy differ per environment. |
| US-04 | **As an** integrator, **I want** first-party browser collectors in this Composer package **so that** I do not publish a separate npm SDK. |
| US-05 | **As a** maintainer, **I want** behavior covered by automated tests **so that** regressions are caught in CI. |
| US-06 | **As a** contributor, **I want** `REQ-*` anchors on scripted flows **so that** PRs cite the same identifiers as this document. |

**Out of scope:** using Device ID as authentication/MFA, legal compliance guarantees, and behavior outside declared PHP/Symfony compatibility ranges.

---

## Bundle functional scope

**Goal:** Symfony bundle providing probabilistic device matching, risk scoring, a collect endpoint, Doctrine persistence, Security integration, and first-party browser collectors. Device IDs are not credentials.

**In scope**

- Documented integration (root `README.md` and `docs/`).
- Configuration in [`CONFIGURATION.md`](CONFIGURATION.md) and runtime behavior in [`USAGE.md`](USAGE.md).
- Web Profiler panel (optional i18n, domain `NowoDeviceIntelligenceBundle`).
- Frontend entrypoint `device-intelligence.min.js` built from TypeScript via Vite.
- Consumer-facing changes in [`CHANGELOG.md`](CHANGELOG.md) and [`UPGRADING.md`](UPGRADING.md).

**Explicit non-goals**

- Device ID as a password, session secret, or MFA factor.
- Enterprise fraud-platform replacement.
- **`demo/`** UI: illustrative unless explicitly documented as stable API.

---

## Validating the functional spec

- Run **`make qa`** or **`make release-check`** as documented in [`CONTRIBUTING.md`](CONTRIBUTING.md).
- Run **PHPUnit** and **Vitest** locally and in CI for code changes.
- New or changed behavior should add or adjust tests under `tests/` and `src/Resources/assets/tests/`.

---

## Requirement identifiers (`REQ-*`)

| ID | Where | What it marks |
| --- | --- | --- |
| `REQ-DEMO-005` | `demo/symfony8/Makefile` | Canonical `make up`: `Waiting…`, `Installing…`, `Demo started at:` from `PORT`. |
| `REQ-DEMO-007` | `demo/Makefile`, demo Makefiles | `update-bundle` before demo tests in `release-check`. |
| `REQ-MAKE-001` | Root `Makefile` | Docker-driven development workflow for the bundle. |
| `REQ-MAKE-008` | Root `Makefile` | `update-deps` via shared `.scripts/`. |

When you change scripted behavior, update the existing `REQ-*` comment or add a new ID and document it here.

---

## Suggested workflow for contributors

1. Clarify behavior in an issue or draft PR (product + Makefiles/demos).
2. Implement with tests and static analysis.
3. Anchor scripts and demos when dev UX changes.
4. Ship integrator docs when configuration or public behavior changes.
5. **Keep Spec Kit artifacts in sync** when production code under `lib/` or `src/` changes:
   - Update [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md) and [`code-inventory.md`](../specs/001-baseline/code-inventory.md).
   - Follow the maintainer checklist in [`SPEC-KIT.md`](SPEC-KIT.md).
   - For **new features**, use Cursor Agent skills (`/speckit-specify`, `/speckit-plan`, `/speckit-tasks`) as documented in SPEC-KIT.

---

## Relationship to Engram / external checklists

[`ENGRAM.md`](ENGRAM.md) covers Nowo-wide documentation checklist items. This document ties together **what the package does**, **how we verify it**, and **local `REQ-*` habits**. Both coexist: Engram for org-level compliance, this file for product + traceability.

---

## GitHub Spec Kit (summary)

This repository uses [GitHub Spec Kit](https://github.com/github/spec-kit) with **Cursor Agent** (`cursor-agent` integration).

| Artifact | Path |
| --- | --- |
| **Operator manual** (install, init, usage) | [`SPEC-KIT.md`](SPEC-KIT.md) |
| Baseline spec | [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md) |
| Code inventory (100%) | [`specs/001-baseline/code-inventory.md`](../specs/001-baseline/code-inventory.md) |
| Constitution | [`.specify/memory/constitution.md`](../.specify/memory/constitution.md) |
| Cursor Agent skills | [`.cursor/skills/`](../.cursor/skills/) (`speckit-*`) |

**Quick start (maintainers):**

```bash
# Install Specify CLI (once per machine) — see SPEC-KIT.md
specify init --here --force --integration cursor-agent --script sh
specify integration list   # Cursor → installed (default)
```

In Cursor Agent, start a new feature with `/speckit-specify <description>`. For day-to-day tooling details, skills reference, folder layout, and troubleshooting, read **[`SPEC-KIT.md`](SPEC-KIT.md)**.

---

## See also

- [`SPEC-KIT.md`](SPEC-KIT.md) — GitHub Spec Kit manual (install, structure, usage)
- [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md)
- [`USAGE.md`](USAGE.md)
- [`CONFIGURATION.md`](CONFIGURATION.md)
- [`CONTRIBUTING.md`](CONTRIBUTING.md)
- [`RELEASE.md`](RELEASE.md)
