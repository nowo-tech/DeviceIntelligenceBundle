# Device Intelligence Bundle

[![CI](https://github.com/nowo-tech/DeviceIntelligenceBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/DeviceIntelligenceBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/device-intelligence-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/device-intelligence-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/device-intelligence-bundle.svg)](https://packagist.org/packages/nowo-tech/device-intelligence-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-7.4%20%7C%208.0%20%7C%208.1%2B-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/device-intelligence-bundle.svg?style=social&label=Star)](https://github.com/nowo-tech/DeviceIntelligenceBundle) [![Coverage](https://img.shields.io/badge/Coverage-99.75%25-brightgreen)](#tests-and-coverage)

> ⭐ **Found this useful?** [Install from Packagist](https://packagist.org/packages/nowo-tech/device-intelligence-bundle) · Give it a **star** on [GitHub](https://github.com/nowo-tech/DeviceIntelligenceBundle) so more developers can find it.

**Device Intelligence Bundle** — probabilistic device matching, risk scoring, a collect endpoint, Doctrine persistence, Security integration, and first-party browser collectors for Symfony. A Device ID is not a credential.

> 📋 **Compatible with Symfony 7.4+ and 8.0–8.1+** — PHP 8.3+ (Symfony 8.x requires PHP 8.4+).

![FrankenPHP Friendly Worker Mode](docs/images/frankenphp-friendly.png)

This bundle is **FrankenPHP worker mode friendly**.

## Features

- Weighted matching (no `sha256(all_signals)` identity hash)
- Named config profiles (`default_profile` + `profiles`)
- Risk engine with explainable rules
- `POST /_device/collect` with origin checks, nonce replay protection, and rate limits
- Doctrine tables with a configurable prefix
- Controller attributes for risk, rate limits, and trusted devices
- Web Profiler panel `nowo_device_intelligence`

## Installation

```bash
composer require nowo-tech/device-intelligence-bundle
```

With **Symfony Flex**, the recipe registers the bundle, config, and collect route. Without Flex, see [docs/INSTALLATION.md](docs/INSTALLATION.md).

```php
// config/bundles.php
return [
    Nowo\DeviceIntelligenceBundle\NowoDeviceIntelligenceBundle::class => ['all' => true],
];
```

```html
<script src="{{ asset('js/device-intelligence.min.js', 'nowo_device_intelligence') }}"></script>
<script>
  const device = new DeviceIntelligence({ endpoint: '/_device/collect' });
  device.collect();
</script>
```

## Requirements

- PHP `>=8.3 <8.6`
- Symfony 7.4, 8.0, and 8.1+
- Optional: Doctrine ORM (default persistence), Messenger, Twig / Web Profiler

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [GitHub Spec Kit](docs/SPEC-KIT.md)

### Additional documentation

- [Quick start](docs/quick-start.md)
- [Collectors](docs/collectors.md)
- [Matching](docs/matching.md)
- [Risk engine](docs/risk-engine.md)
- [Trusted devices](docs/trusted-devices.md)
- [Privacy](docs/privacy.md)
- [Rate limiting](docs/rate-limiting.md)
- [Profiler](docs/profiler.md)
- [Custom rules](docs/custom-rules.md)
- [Custom collectors](docs/custom-collectors.md)
- [Scaling](docs/scaling.md)
- [PSR evaluation](docs/PSR.md)
- [Coverage policy](docs/COVERAGE.md)
- [Demo FrankenPHP](docs/DEMO-FRANKENPHP.md)
- [GitHub CI](docs/GITHUB_CI.md)
- [GitHub About](docs/GITHUB.md)

## Tests and coverage

PHP line coverage target: **99.75%** of includable `src/` and `lib/` (gate **≥ 99%**). TypeScript line coverage: **95.28%** (gate **≥ 90%**).

```bash
make test
make test-coverage
make test-ts
make qa
```

| Language | Latest reported |
| --- | --- |
| PHP | 99.75% lines (Clover `coverage.xml`, CI) |
| TypeScript | 95.28% lines (`pnpm run test:coverage`) |

## License

MIT © 2026 Héctor Franco Aceituno / Nowo.tech
