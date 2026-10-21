# Changelog

All notable changes to `tecclub/releasekit` will be documented in this file.

## v0.1.0 - 2026-10-21

First release of ReleaseKit: a headless Laravel package for app releases, build artifacts, downloads and version checks across Android, iOS, macOS, Windows, Linux and any platform you add in config.

**Requirements:** PHP 8.3+, Laravel 12 or 13, SQLite / MySQL / MariaDB / PostgreSQL.

### Highlights

- **Releases** per platform + channel + version with a `draft` → `published` → `yanked` lifecycle, **artifacts** (optionally per architecture) and **store / external links**.
- **Tracks** with an explicit current release and a minimum supported version. Publishing is not going live; promoting is. Rollback = promote an older release.
- **Actions for every write**, with admin-readable `ReleaseKitException`s and events dispatched after commit.
- **Version check** returning `up_to_date`, `update_available`, `update_required` or `blocked`, plus **downloads** and **release history**, available through the `ReleaseKit` facade and an opt-in HTTP API with a publishable route stub.
- **Delivery** by streaming, temporary signed URLs or public URLs, with automatic fallbacks.
- **Protected channels** (e.g. beta) behind a Gate ability, with temporary signed download URLs.
- **Uploads**: streamed `UploadedFile`s or direct-to-storage paths (1 GB+ tested with flat memory), queued SHA-256 checksums and a `releasekit:prune-orphans` command.
- **Caching**: a warm version check runs zero queries; model observers keep it correct.
- **Optional analytics**: daily aggregate counts of checks and downloads, with no IPs, user agents or device IDs.
- **Laravel Boost** guideline and `releasekit` skill, plus a client integration contract with a Flutter example.

### Install

```bash
composer require tecclub/releasekit
php artisan vendor:publish --tag=releasekit-config
php artisan migrate
php artisan vendor:publish --tag=releasekit-routes   # unless routes.register = true

```
See the [README](https://github.com/TecClubb/laravel-releasekit#readme) for the full guide.

## Unreleased

First release candidate.

- Releases per platform + channel + version with a draft → published → yanked lifecycle, artifacts (per architecture), store and external links, and tracks with an explicit current release and minimum supported version.
- Actions for every write, with admin-readable `ReleaseKitException`s and after-commit events.
- Version check (`up_to_date`, `update_available`, `update_required`, `blocked`), downloads and release history, as services, a facade, and an opt-in HTTP API with a publishable route stub.
- Stream, signed and public delivery with fallbacks; protected channels behind a Gate with temporary signed download URLs.
- Streamed uploads or direct-to-storage paths (1 GB+ tested), queued checksums, orphan pruning command.
- Observer-cleared cache: a warm version check runs zero queries.
- Optional daily aggregate analytics without personal data.
- Laravel Boost guideline and `releasekit` skill; client integration contract for app teams.
