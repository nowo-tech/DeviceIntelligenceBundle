# Upgrade Guide

This guide provides step-by-step instructions for upgrading Device Intelligence Bundle between versions.

## Table of contents


- [From 1.0.1 to 1.1.0](#from-101-to-110)
- [General upgrade process](#general-upgrade-process)
- [To 1.0.1](#to-101)
- [To 1.0.0 (initial release)](#to-100-initial-release)
- [Future versions](#future-versions)
- [Getting help](#getting-help)

## From 1.0.1 to 1.1.0

No breaking changes. **No application upgrade steps.**

```bash
composer update nowo-tech/device-intelligence-bundle
```

## From 1.0.1 to 1.1.0

No breaking changes. **No application upgrade steps.**

```bash
composer update nowo-tech/device-intelligence-bundle
```

## General upgrade process

1. **Backup** your `config/packages/nowo_device_intelligence.yaml` and database.
2. **Review** [CHANGELOG.md](CHANGELOG.md) for breaking changes.
3. **Update**: `composer update nowo-tech/device-intelligence-bundle`
4. **Clear cache**: `php bin/console cache:clear`
5. **Rebuild assets**: `php bin/console assets:install` (IIFE) or `pnpm run build` in a Pentatrion Vite app.
6. **Test** collect (`POST /_device/collect`) and matching in your environments.

## To 1.0.1

Patch release. No required configuration changes for hosts already on **1.0.0**.

```bash
composer update nowo-tech/device-intelligence-bundle:^1.0
php bin/console cache:clear
php bin/console assets:install
```

### Behaviour you may notice

- After `collect()`, the Web Profiler panel on the **next HTML request** can show the device when the `di_obs` cookie is present, even if `observe_on_every_request` is `false`. Set it to `true` (as the demo does) if you want hydration on every request.
- Profiler labels are translated (domain `NowoDeviceIntelligenceBundle`). Install `symfony/translation` if the panel should not fall back to keys. Hosts that **override** the profiler Twig template must merge the `|trans` usage.
- The published IIFE logs a one-line `script loaded` message in the browser console.

### Demo only

The Symfony 8 demo now uses **Pentatrion Vite** + **pnpm** (`make assets`). Integrators using `asset('js/device-intelligence.min.js', 'nowo_device_intelligence')` are unchanged.

### Breaking changes

None for the public PHP API or default YAML.

## To 1.0.0 (initial release)

This is the first stable release. Install or require the package:

```bash
composer require nowo-tech/device-intelligence-bundle:^1.0
```

### Requirements

- PHP `>=8.3` (`<8.6`). Symfony **8.0** and **8.1+** require **PHP 8.4+**.
- Symfony **7.4**, **8.0**, or **8.1+**.
- Doctrine ORM when `doctrine.enabled: true` (the default).

### Enable and configure

1. Register the bundle (or use the Symfony Flex recipe — see [Installation](INSTALLATION.md)).
2. Import attribute routes (Flex does this) or `config/routes/nowo_device_intelligence.yaml`.
3. Run `php bin/console assets:install`.
4. Load the JS via `asset('js/device-intelligence.min.js', 'nowo_device_intelligence')`.
5. Create Doctrine tables when persistence is enabled.

See [Installation](INSTALLATION.md) and [Configuration](CONFIGURATION.md).

### Breaking changes

None — there is no prior stable release.

## Future versions

For upgrade instructions between versions, see the [Changelog](CHANGELOG.md).

## Getting help

- [Usage](USAGE.md) — integration examples
- [Configuration](CONFIGURATION.md) — all options
- [GitHub Issues](https://github.com/nowo-tech/DeviceIntelligenceBundle/issues)
