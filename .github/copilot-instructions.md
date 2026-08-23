## AI contribution guidelines — Device Intelligence Bundle

Follow these rules when contributing PHP, TypeScript, Twig, and documentation to this repository.

---

## Project scope

- **Type:** Standalone Symfony bundle (`nowo-tech/device-intelligence-bundle`).
- **PHP:** `>=8.3 <8.6` with `declare(strict_types=1);` in every PHP file.
- **Symfony:** **7.4**, **8.0**, and **8.1+** (`^7.4 || ^8.0` on `symfony/*` constraints). Symfony 8 requires PHP 8.4+.
- **Core:** Framework-agnostic domain in `lib/` (`Nowo\DeviceIntelligence\`). Symfony adapters in `src/` (`Nowo\DeviceIntelligenceBundle\`).
- **Frontend:** TypeScript + Vite in `src/Resources/assets/`; IIFE `src/Resources/public/js/device-intelligence.min.js`.
- **Language:** PHPDoc, inline comments, and user-facing docs in **English** only.

---

## PHP standards

- PSR-12 + Symfony coding standards; run `make cs-check` before finishing.
- Use **strict comparisons** (`===`) and constructor injection.
- Prefer `final` classes; keep BC on public config keys, route names, and the collect protocol.
- Wire services in `src/Resources/config/services.php`.
- Preserve the DI extension alias: `nowo_device_intelligence`.
- Entity table prefix via `doctrine.table_prefix` (default `device_intelligence_`).
- Controllers use `#[Route]` attributes. Do not add a route loader.

---

## Bundle-specific conventions

- Device ID is **not** a credential. Matching is weighted; never `sha256(all_signals)`.
- Route name: `nowo_device_intelligence_collect` (`POST /_device/collect`).
- Twig namespace: `NowoDeviceIntelligenceBundle`.
- Asset package: `nowo_device_intelligence` (`base_path: /bundles/nowodeviceintelligence`).
- Load JS with `asset('js/device-intelligence.min.js', 'nowo_device_intelligence')`.
- Logs: hashed device/observation ids only; never full signal bags.
- Time: inject `Psr\Clock\ClockInterface` (no `new DateTimeImmutable()` in handlers).

---

## Tests and quality

- PHPUnit target: **~100% line coverage** on `src/` + `lib/` (gate ≥99%); justify exclusions in `docs/COVERAGE.md`.
- TypeScript tests via Vitest: `make test-ts` (≥90% lines).
- Full gate: `make release-check` (style, static analysis, PHP + TS coverage, demos).
- Use real collaborators or focused test doubles; avoid mocking `final` classes when PHPUnit blocks it.

---

## Documentation

- Keep `README.md` badges and the **Documentation** section aligned with `docs/`.
- Update `docs/CHANGELOG.md` and `docs/UPGRADING.md` for user-visible changes.
- Flex recipe lives in `.symfony/recipe/nowo-tech/device-intelligence-bundle/1.0/`; document Flex steps in `docs/INSTALLATION.md`.

---

## Do not

- Treat Device ID as authentication or MFA.
- Commit secrets, `.env` files, or demo `var/` caches.
- Add Spanish PHPDoc or comments in `src/` / `lib/`.
- Publish a separate npm package for collectors.
- Touch unrelated files when fixing a focused issue.
