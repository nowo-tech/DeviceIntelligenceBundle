# Installation

## Table of contents

- [Requirements](#requirements)
- [Composer](#composer)
- [Enable the bundle](#enable-the-bundle)
- [Routes](#routes)
- [Database](#database)
- [Client assets](#client-assets)
  - [IIFE (any host)](#iife-any-host)
  - [Pentatrion Vite (Symfony apps)](#pentatrion-vite-symfony-apps)
- [Twig template overrides](#twig-template-overrides)
- [Optional packages](#optional-packages)

## Requirements

- PHP `>=8.3 <8.6`
- Symfony `^7.4` or `^8.0` (covers 8.1+)
- Twig Extra: `twig/extra-bundle` and `twig/string-extra` (required by the bundle and installed with it, REQ-TWIG-004).
  Without Flex, register `Twig\Extra\TwigExtraBundle\TwigExtraBundle` in `config/bundles.php`

## Composer

With **Symfony Flex** (recommended):

```bash
composer require nowo-tech/device-intelligence-bundle
```

The Flex recipe (`.symfony/recipe/nowo-tech/device-intelligence-bundle/1.0/`) registers the bundle, default YAML, and the collect route import.

Without Flex, add the bundle to `config/bundles.php` and copy the YAML/routes from the recipe (or this document).

Local monorepo checkout:

```json
{
    "repositories": [
        { "type": "path", "url": "../DeviceIntelligenceBundle" }
    ],
    "require": {
        "nowo-tech/device-intelligence-bundle": "*@dev"
    }
}
```

## Enable the bundle

```php
// config/bundles.php
return [
    Nowo\DeviceIntelligenceBundle\NowoDeviceIntelligenceBundle::class => ['all' => true],
];
```

Create `config/packages/nowo_device_intelligence.yaml`:

```yaml
nowo_device_intelligence:
    enabled: true
    doctrine:
        enabled: true
        table_prefix: device_intelligence_
```

## Routes

The collect POST route is `nowo_device_intelligence_collect` (`POST /_device/collect`), declared with `#[Route]` on `CollectController`.

Flex imports attributes. Manual import:

```yaml
# config/routes/nowo_device_intelligence.yaml
nowo_device_intelligence:
    resource: '@NowoDeviceIntelligenceBundle/Resources/config/routes.php'
```

When `endpoint.enabled` is false the action returns HTTP 404.

## Database

When `doctrine.enabled` is true, map the entities and run your usual schema/migration workflow. Table names are prefixed (`device_intelligence_device`, `device_intelligence_observation`, …).

```bash
php bin/console doctrine:schema:update --dump-sql
```

## Client assets

Browser collectors live in `src/Resources/assets` (TypeScript, **pnpm** + Vite). Rebuild in this repository with `make assets`. There is no public npm package.

### IIFE (any host)

The published file is `src/Resources/public/js/device-intelligence.min.js`. The bundle registers the Symfony asset package `nowo_device_intelligence` with `base_path: /bundles/nowodeviceintelligence` (REQ-ASSETS-004).

After `php bin/console assets:install`:

```html
<script src="{{ asset('js/device-intelligence.min.js', 'nowo_device_intelligence') }}"></script>
```

### Pentatrion Vite (Symfony apps)

Install `pentatrion/vite-bundle` and `vite-plugin-symfony`, then import the sources from `src/Resources/assets` in your Vite entry (see `demo/symfony8/`). Use `vite_entry_script_tags('app')` in Twig. `pnpm` is required (`packageManager` in the demo `package.json`).

## Twig template overrides

Templates live under `src/Resources/views/` with namespace **`NowoDeviceIntelligenceBundle`**. `TwigPathsPass` registers
`templates/bundles/NowoDeviceIntelligenceBundle/` first (when it exists) and then the bundle views, so application files
**always win**.

| `<subpath>` | Purpose |
| --- | --- |
| `Collector/device_intelligence.html.twig` | Web profiler panel and toolbar item (`nowo_device_intelligence`) |
| `Icon/device-intelligence.svg` | Icon used by the profiler panel |

**Procedure:** copy `vendor/nowo-tech/device-intelligence-bundle/src/Resources/views/<subpath>` to
`templates/bundles/NowoDeviceIntelligenceBundle/<subpath>`, edit it, then `php bin/console cache:clear`. Logical names:
`@NowoDeviceIntelligenceBundle/<subpath>`.

**Override vs upgrade (freeze rule).** An override does not pick up vendor changes for that `<subpath>` until you remove
or merge it: the file stays frozen on every `composer update` while the rest of the bundle upgrades. The panel is a dev
tool, so prefer leaving it as is and reading the data through the collector service or the events. To recover the vendor
version, delete the override, clear the cache and diff your copy against the new vendor file before re-applying changes.

## Optional packages

| Package | Purpose |
| --- | --- |
| `symfony/twig-bundle` + `symfony/web-profiler-bundle` | Profiler panel `nowo_device_intelligence` |
| `symfony/translation` | Profiler UI strings (domain `NowoDeviceIntelligenceBundle`, 7 locales) |
