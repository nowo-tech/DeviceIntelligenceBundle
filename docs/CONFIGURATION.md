# Configuration

## Table of contents

- [Profiles](#profiles)
- [Collect endpoint](#collect-endpoint)
- [Validation](#validation)
- [Persistence](#persistence)
- [Messenger](#messenger)
- [Translations](#translations)

Alias: `nowo_device_intelligence`.

Named **profiles** hold collectors, matching, risk, privacy, trusted devices, and rate-limit policies. Root-level legacy keys `collectors`, `matching`, `risk`, `trusted_devices`, `privacy`, and `rate_limit` are normalized into `profiles.default`.

## Profiles


```yaml
nowo_device_intelligence:
    enabled: true
    default_profile: default
    profiles:
        default:
            collectors: [audio, canvas, webgl, screen, navigator, timezone, client_hints, capabilities, automation, fonts]
            matching:
                minimum_confidence: 0.75
                weights:
                    audio: 0.12
                    canvas: 0.18
                    webgl: 0.20
                    platform: 0.10
                    screen: 0.08
                    timezone: 0.05
                    hardware: 0.07
                    browser_capabilities: 0.10
                    client_hints: 0.10
                candidate_limit: 64
                lookback: P180D
                on_low_confidence: new_device
            risk:
                enabled: true
                levels:
                    low: 0
                    medium: 30
                    high: 65
                    critical: 90
                decisions:
                    observe: 40
                    step_up: 70
                    block: 90
                rules:
                    new_device: { enabled: true }
                    trusted_device: { enabled: true }
            trusted_devices:
                enabled: true
                default_ttl: P90D
            privacy:
                mode: balanced   # strict | balanced | full
                hash_ip: true
                store_raw_ip: false
                store_user_agent: true
                high_entropy_consent: true
            rate_limit:
                policies:
                    collect:
                        limit: 60
                        interval: '1 minute'
    endpoint:
        enabled: true
        path: /_device/collect
        csrf: origin           # origin | double_submit | none
        max_payload_bytes: 65536
        timestamp_skew: 300
        replay_protection: true
        allowed_origins: []    # extra hosts accepted by csrf: origin (see below)
        response:
            device_id: true
            confidence: true
            risk: true
            token: false
    doctrine:
        enabled: true
        table_prefix: device_intelligence_
    cache:
        pool: cache.app
    messenger:
        enabled: false
    profiler: true
    observe_on_every_request: false
    token_cookie:
        name: di_obs
        path: /
        domain: null
        secure: auto
        httponly: true
        samesite: lax
    token_ttl: 3600
    ip_salt: ''   # empty → kernel.secret
```

## Collect endpoint

- `endpoint.csrf: origin` (default) requires an `Origin` (or `Referer`) header whose **host** equals the request host or one of `endpoint.allowed_origins`.
- `endpoint.allowed_origins` lists extra hosts allowed to POST to the collect endpoint (e.g. when the page is served from `www.example.com` and the API from `api.example.com`). Entries may be full origins (`https://www.example.com`) or bare hosts (`www.example.com`); only the host part is compared (scheme and port are ignored). Default: `[]` (same host only).
- `nonce` must match `^[A-Za-z0-9_-]{8,128}$` (the bundled browser client sends 32 hex chars). It is required when `replay_protection: true`.
- When the `collect` rate-limit policy is exceeded the endpoint answers **429 Too Many Requests**.

## Validation

- Rate-limit `interval` accepts relative strings (`'60 seconds'`, `'15 minutes'`, `'1 day'`, `'2 weeks'`) or ISO 8601 durations (`'PT15M'`). Unparseable or non-positive intervals are rejected.
- Matching **weights must sum to ~1.0** and each weight is in `[0, 1]`.
- Risk scores and decision thresholds are `0..100`.
- Unknown collector names are rejected. Allowed: `audio`, `canvas`, `webgl`, `screen`, `navigator`, `timezone`, `client_hints`, `capabilities`, `automation`, `fonts`.

## Persistence

- `doctrine.enabled: true` (default) wires Doctrine repositories that implement the core ports.
- `doctrine.enabled: false` uses the core `InMemory*` repositories (request-scoped; for tests).

## Messenger

Set `messenger.enabled: true` to register `CleanupMessage` and `RecalculateStabilityMessage` handlers (`#[AsMessageHandler]`). Transport, retries, and `failure_transport` are **host-owned** (`framework.messenger`). Default Symfony retry policy applies when the host uses async transports; the demo/sync bus runs handlers inline with no retry. Handlers are idempotent: cleanup deletes observations older than the cutoff; recalculate overwrites stored stability. Failed async messages should land on the host failure transport — this bundle does not register one.

## Translations

Domain: **`NowoDeviceIntelligenceBundle`**. Required locales: `en`, `es`, `it`, `fr`, `pt`, `de`, `nl`.

Catalogues live in `src/Resources/translations/`. Host apps override keys in `translations/NowoDeviceIntelligenceBundle.<locale>.yaml`. The Web Profiler panel uses this domain; collect JSON, console commands, and risk reason ids stay untranslated.
