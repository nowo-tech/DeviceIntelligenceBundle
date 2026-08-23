# Security

## Table of contents

- [Device ID is not a credential](#device-id-is-not-a-credential)
- [What this bundle stores](#what-this-bundle-stores)
- [What we never log](#what-we-never-log)
- [Collect endpoint](#collect-endpoint)
- [Trust](#trust)
- [Controller guards](#controller-guards)
- [FrankenPHP](#frankenphp)
- [AI security audit](#ai-security-audit)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)

## Device ID is not a credential

A Device ID (ULID) identifies a **probable** browser/device cluster. It is:

- **Not** a password, session secret, or API key
- **Not** an authentication factor and **not** MFA
- **Not** sufficient to authorize a privileged action

Attackers control the client. Signals can be spoofed, replayed, or copied. Use device intelligence for **risk scoring and step-up**, never as a login replacement.

## What this bundle stores

- Compact derived signals (digests, enums, short maps). Never raw canvas pixels, audio PCM, or font lists dumped in full in logs.
- **Hashed IP** by default (`privacy.hash_ip: true`). Raw IP is **not** stored unless you explicitly set `privacy.store_raw_ip: true`.
- Observation cookie payload: `observation_id|iat|exp|nonce` signed with HMAC. **Not a fingerprint.**

## What we never log

Logs include device ULID, observation ULID, risk score, and high-level reasons. **Full signal bags are never logged.** The profiler shows **truncated summaries** only.

## Collect endpoint

- CSRF: `origin` (default), `double_submit`, or `none` (only behind another gateway).
- Payload size cap (`max_payload_bytes`).
- Schema `v=1` and timestamp skew.
- Nonce replay protection via cache.
- Rate limiting on hashed IP (`collect` policy).

## Trust

Login **associates** a user with the current device and increments velocity. Trust is **never automatic**. Grant trust through `DeviceTrustService` after an explicit user action (and preferably after MFA).

## Controller guards

```php
use Nowo\DeviceIntelligenceBundle\Attribute\DeviceRisk;
use Nowo\DeviceIntelligenceBundle\Attribute\RequireTrustedDevice;

#[DeviceRisk(max: 70)]
#[RequireTrustedDevice]
public function payout(): void {}
```

Failures return **403**. These attributes reduce risk; they do not replace authorization voters or MFA.

## FrankenPHP

Matchers and analysis collaborators have no static mutable state. In-memory repositories and `DeviceIntelligence` are **not shared** across worker requests.

## AI security audit

This document is the maintained threat model for the bundle (REQ-SEC-004). Maintainers re-read it before each release and after changes to collect, cookies, or logging. There is no separate unpublished audit file.

## Reporting a vulnerability

Report security issues **privately**:

1. Do **not** open a public GitHub issue for security-sensitive bugs.
2. Use [GitHub Security Advisories](https://github.com/nowo-tech/DeviceIntelligenceBundle/security/advisories) or email **hectorfranco@nowo.tech**.
3. Include steps to reproduce, affected versions, and impact.
4. We will acknowledge and coordinate disclosure after a fix is available.

See also [`.github/SECURITY.md`](../.github/SECURITY.md) for supported versions and reporting policy.

## Release security checklist (12.4.1)

Before each release, the maintainer confirms each item (note in the release PR or tag message is sufficient).

| Item | Check |
| --- | --- |
| `docs/SECURITY.md` and `.github/SECURITY.md` up to date | ☐ |
| `.env` / secrets in `.gitignore`; no credentials in repo | ☐ |
| Flex recipe / published config contains no secrets | ☐ |
| Collect payload validation, origin/CSRF, nonce replay, payload size cap | ☐ |
| Logs hash device/observation ids; never full signal bags | ☐ |
| `composer audit` run; known issues reviewed | ☐ |
| Observation cookie is HMAC-signed; Device ID is not a credential | ☐ |
| Collect and demo routes documented; rate limits enabled by default | ☐ |
| Rate limits / DoS: collect endpoint is rate-limited; consumer apps should also apply HTTP limits | ☐ |
