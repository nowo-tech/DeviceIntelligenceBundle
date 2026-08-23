# Coverage policy

## Table of contents

- [PHP line coverage gate](#php-line-coverage-gate)
- [TypeScript line coverage gate](#typescript-line-coverage-gate)
- [Justified exclusions](#justified-exclusions)
- [How to refresh](#how-to-refresh)

## PHP line coverage gate

`make coverage-check` / `composer coverage-check` enforce **≥ 99%** line coverage on the PHPUnit includable `src/` and `lib/` set (`REQ-TEST-003` / `REQ-TEST-006`).

Published README percentage must match the latest coverage text / CI artifact.

## TypeScript line coverage gate

`make test-ts` / `pnpm run test:coverage` enforce **≥ 90%** lines (`vitest.config.ts`).

## Justified exclusions

`phpunit.xml.dist` excludes `src/Resources` (Twig, compiled JS, PHP DI files that are not executable application code). No other `<source><exclude>` entries.

`lib/Device/Ulid.php` marks the defensive “encoded string is not 26 characters” branch with `@codeCoverageIgnore`. Crockford encoding of 16 bytes always yields at least 26 characters; that throw is an invariant guard, not a runtime path.

Vitest coverage excludes `src/Resources/assets/src/types/**` (TypeScript interfaces only). The enforced gate is **≥ 90% lines** (statements and functions share the same floor; branches are reported with a 70% floor).

Do **not** add new `@codeCoverageIgnore` or PHPUnit exclusions without updating this document.

## How to refresh

```bash
make coverage-check
make test-ts
```
