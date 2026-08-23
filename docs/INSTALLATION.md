# Installation

## Table of contents

- [Requirements](#requirements)
- [Composer](#composer)
- [Enable the bundle](#enable-the-bundle)
- [Routes](#routes)
- [Database](#database)
- [Client assets](#client-assets)
- [Twig template overrides](#twig-template-overrides)
- [Optional packages](#optional-packages)

## Requirements

- PHP `>=8.3 <8.6`
- Symfony `^7.4` or `^8.0` (covers 8.1+)

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

Browser collectors live in `src/Resources/assets` (Vite). The published IIFE is `src/Resources/public/js/device-intelligence.min.js`.

The bundle registers the Symfony asset package `nowo_device_intelligence` with `base_path: /bundles/nowodeviceintelligence` (REQ-ASSETS-004).

After `php bin/console assets:install`:

```html
<script src="{{ asset('js/device-intelligence.min.js', 'nowo_device_intelligence') }}"></script>
```

Rebuild in this repository with `make assets` (`pnpm build`). There is no public npm package.

## Twig template overrides

Profiler (and any future) templates live under `src/Resources/views/` with namespace **`NowoDeviceIntelligenceBundle`**.

Override from the host app:

```
templates/bundles/NowoDeviceIntelligenceBundle/Collector/device_intelligence.html.twig
```

A full-file override hides vendor updates for that path until you merge them. Prefer small diffs.

Logical names: `@NowoDeviceIntelligenceBundle/Collector/device_intelligence.html.twig`.

## Optional packages

| Package | Purpose |
| --- | --- |
| `symfony/twig-bundle` + `symfony/web-profiler-bundle` | Profiler panel `nowo_device_intelligence` |
