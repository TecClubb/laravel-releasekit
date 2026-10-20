# ReleaseKit for Laravel

[![Tests](https://img.shields.io/github/actions/workflow/status/TecClubb/laravel-releasekit/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/TecClubb/laravel-releasekit/actions?query=workflow%3Arun-tests+branch%3Amain)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/TecClubb/laravel-releasekit/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/TecClubb/laravel-releasekit/actions?query=workflow%3APHPStan+branch%3Amain)

A headless Laravel package for managing app releases, build artifacts, downloads, and version checks across platforms (Android, iOS, macOS, Windows, Linux — and anything else you add in config).

> **Status:** under active development. See [docs/PROGRESS.md](docs/PROGRESS.md) for what is built so far and [docs/plan.md](docs/plan.md) for the full design.

## What it does

- Releases per platform and channel, each with version, build number, notes and status (`draft` → `published` → `yanked`).
- Build files (artifacts) per release, optionally per architecture, plus store and external links.
- An explicit **current release** and **minimum supported version** per platform + channel.
- A **version check** that tells apps whether they are up to date, can update, must update, or are blocked.
- A **downloads** endpoint for websites, with streaming, signed or public delivery.
- Public or protected channels, optional analytics, and no admin UI — your app builds its own on top of the Actions.

## Requirements

- PHP 8.3+
- Laravel 12 or 13

## Installation

```bash
composer require tecclub/releasekit
php artisan vendor:publish --tag=releasekit-config
php artisan migrate
```

The package loads its migrations automatically. To manage them yourself, publish them and opt out in a service provider's `register()` method:

```bash
php artisan vendor:publish --tag=releasekit-migrations
```

```php
\TecClub\ReleaseKit\ReleaseKit::ignoreMigrations();
```

Optional analytics table:

```bash
php artisan vendor:publish --tag=releasekit-analytics-migrations
php artisan migrate
```

## Testing

```bash
composer test
composer analyse
```

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).

Built by [TecClub](https://tecclubx.com).
