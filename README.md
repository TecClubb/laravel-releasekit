# ReleaseKit for Laravel

[![Tests](https://img.shields.io/github/actions/workflow/status/TecClubb/laravel-releasekit/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/TecClubb/laravel-releasekit/actions?query=workflow%3Arun-tests+branch%3Amain)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/TecClubb/laravel-releasekit/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/TecClubb/laravel-releasekit/actions?query=workflow%3APHPStan+branch%3Amain)

A headless Laravel package for managing app releases, build artifacts, downloads and version checks across platforms: Android, iOS, macOS, Windows, Linux, and anything else you add in config.

- **Releases** per platform and channel, with version, build number, notes and status (`draft` → `published` → `yanked`).
- **Artifacts** (build files) per release, optionally per architecture, plus **store and external links**.
- An explicit **current release** and **minimum supported version** per platform + channel.
- A **version check** that tells apps whether they are up to date, can update, must update, or are blocked.
- A **downloads** endpoint for websites, a **release history** endpoint, and file delivery by streaming, signed URLs or public URLs.
- **Public or protected channels** (e.g. a beta channel behind a Gate), optional **analytics**, and a **Laravel Boost** guideline and skill for AI agents.

There is no admin UI: your app builds its own on top of the Actions.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Concepts](#concepts)
- [Configuration](#configuration)
- [Managing releases (Actions)](#managing-releases-actions)
- [Exposing the API](#exposing-the-api)
- [Using it from PHP (facade)](#using-it-from-php-facade)
- [Protected channels](#protected-channels)
- [File storage and delivery](#file-storage-and-delivery)
- [Analytics](#analytics)
- [Client apps](#client-apps)
- [Laravel Boost](#laravel-boost)
- [Testing](#testing)

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- SQLite, MySQL/MariaDB or PostgreSQL

## Installation

```bash
composer require tecclub/releasekit
php artisan vendor:publish --tag=releasekit-config
php artisan migrate
php artisan vendor:publish --tag=releasekit-routes   # unless you set routes.register = true
```

The package loads its migrations automatically. To manage them yourself, publish them and opt out in a service provider's `register()` method:

```bash
php artisan vendor:publish --tag=releasekit-migrations
```

```php
\TecClub\ReleaseKit\ReleaseKit::ignoreMigrations();
```

Run a queue worker: a queued job computes artifact checksums, and file deletions are queued.

## Concepts

| Term | Meaning |
|---|---|
| **Release** | One row per platform + channel + version, e.g. *Android 1.2.0 (stable)*. Starts as a `draft`. |
| **Artifact** | A build file attached to a release (`apk`, `exe`, `dmg`, …), optionally for one architecture. `null` architecture = universal. |
| **Link** | A store listing or external URL. Either **permanent** for a platform (e.g. your Play Store page) or tied to one release. |
| **Track** | One per platform + channel. Points at the **current release** (what clients get) and holds the **minimum supported version**. |
| **Channel** | `stable`, `beta`, … Each is `public` or `protected`. |

**Publishing is not going live.** A release becomes what clients see only when you **promote** it onto its track. Rolling back means promoting an older release.

**Versions** have 2 to 4 numeric parts (`1.2`, `1.2.0`, `1.2.0.4`), and `1.2` equals `1.2.0`. There are no `-beta` suffixes; use channels. **Every build that reaches users needs a new version**: a hotfix of `1.2.0+45` ships as `1.2.1`, otherwise clients on both builds report `1.2.0` and can't be told apart.

## Configuration

`config/releasekit.php` defines channels, platforms (architectures, formats, which formats are downloadable, stores), stores, storage, delivery, routes, access, cache, table and model names, and analytics. Adding a platform, architecture, format, store or channel is **a config change, never a migration**:

```php
'platforms' => [
    'android' => [
        'label' => 'Android',
        'architectures' => ['arm64-v8a', 'armeabi-v7a', 'x86_64'],
        'formats' => ['apk', 'aab'],
        'downloadable_formats' => ['apk'], // aab is stored for archive, never offered
        'stores' => ['play_store'],
    ],
],
```

Every key is documented in [`resources/boost/skills/releasekit/references/config.md`](resources/boost/skills/releasekit/references/config.md).

## Managing releases (Actions)

Every write goes through an Action in `TecClub\ReleaseKit\Actions`. They enforce the rules (status transitions, valid platforms/formats/stores, one current release per track, …) and throw a `ReleaseKitException` with an admin-readable message when a rule is broken.

```php
use TecClub\ReleaseKit\Actions\{CreateRelease, AttachArtifact, AddLink, PublishRelease, PromoteRelease, YankRelease, UpdateTrackMinimum};
use TecClub\ReleaseKit\Exceptions\ReleaseKitException;

try {
    $release = app(CreateRelease::class)->handle(
        platform: 'android', version: '1.2.0', channel: 'stable',
        buildNumber: '45', releaseNotes: 'Faster sync and bug fixes.',
    );

    app(AttachArtifact::class)->handle($release, $request->file('apk'), format: 'apk', architecture: 'arm64-v8a');
    app(AddLink::class)->handle(platform: 'android', store: 'play_store', url: 'https://play.google.com/store/apps/details?id=com.example'); // permanent
    app(PublishRelease::class)->handle($release);

    $warnings = app(PromoteRelease::class)->warnings($release); // show these, then confirm
    app(PromoteRelease::class)->handle($release);               // now clients see 1.2.0
} catch (ReleaseKitException $e) {
    return back()->withErrors(['release' => $e->getMessage()]);
}

// Something is wrong with 1.2.0: roll back and block it
app(YankRelease::class)->handle($release, reason: 'Crash on launch', rollbackTo: $previous);

// Force everyone below 1.1.0 to update
app(UpdateTrackMinimum::class)->handle(platform: 'android', channel: 'stable', version: '1.1.0');
```

| Action | Purpose |
|---|---|
| `CreateRelease`, `UpdateRelease`, `DeleteDraftRelease` | Drafts are fully editable and deletable. Published releases accept notes and metadata, yanked ones only notes. Published and yanked releases are never deleted. |
| `PublishRelease`, `PromoteRelease` (+ `warnings()`) | Publish, then make current. Warnings flag e.g. a missing architecture the current release has. |
| `YankRelease`, `UnyankRelease` | Pull a release (clients on it get `blocked`). Yanking the current release needs `rollbackTo`. |
| `UpdateTrackMinimum` | Set or clear the minimum supported version. |
| `AttachArtifact`, `UpdateArtifact`, `DeleteArtifact` | Build files. |
| `AddLink`, `UpdateLink`, `DeleteLink` | Store and external links. |

Read with the models and their scopes:

```php
use TecClub\ReleaseKit\Models\Release;

Release::forPlatform('android')->forChannel('stable')->published()->latest('published_at')->get();
```

Events for notifications and audit logs (all dispatched after commit): `ReleaseCreated`, `ReleasePublished`, `ReleasePromoted`, `ReleaseYanked`, `ReleaseUnyanked`, `TrackMinimumUpdated`, `ArtifactAttached`, `ArtifactDownloaded`.

To add columns or behaviour, extend a model and register it under `releasekit.models.*`.

Full reference: [`actions.md`](resources/boost/skills/releasekit/references/actions.md).

## Exposing the API

Routes are **not registered by default**. Pick one:

**A. Auto-register.** Set `releasekit.routes.register` to `true`. Routes use `routes.prefix` (default `api/releasekit`), `routes.name` (`releasekit.`) and `routes.middleware` (`api`, `throttle:120,1`). Turn single endpoints off under `routes.endpoints`.

**B. Register them yourself** (e.g. inside an API versioning setup):

```bash
php artisan vendor:publish --tag=releasekit-routes
```

```php
Route::prefix('api/v1/releasekit')
    ->middleware(['api', 'throttle:120,1'])
    ->group(base_path('routes/releasekit.php'));
```

Keep the route names: download URLs are generated from `releasekit.artifacts.download` (the `routes.name` prefix + `artifacts.download`). The stub reads `routes.name` itself, so don't wrap it in another `->name()` prefix. It adds no middleware either, so bring your own throttle.

| Endpoint | Purpose |
|---|---|
| `GET check?platform=android&version=1.1.0[&channel=][&architecture=]` | Version check: `up_to_date`, `update_available`, `update_required` or `blocked`, plus the current release and its downloads |
| `GET downloads[?platform=][&channel=][&architecture=]` | Current downloads for every platform, for a website |
| `GET releases?platform=android[&channel=][&per_page=]` | Paginated release history / changelog |
| `GET artifacts/{id}/download` | Delivers a file |

Validation errors are always JSON `422`, protected channels without access `403`. Response shapes: [`api.md`](resources/boost/skills/releasekit/references/api.md).

The controllers are thin. To change a response shape, publish copies with `php artisan vendor:publish --tag=releasekit-controllers` (into `app/Http/Controllers/ReleaseKit`) and point your routes at them. Copies stop receiving package fixes.

## Using it from PHP (facade)

When the website lives in the same Laravel app, skip HTTP:

```php
use TecClub\ReleaseKit\Facades\ReleaseKit;

$result    = ReleaseKit::check(platform: 'android', version: '1.1.0', channel: 'stable', architecture: 'arm64-v8a');
$result->status;          // CheckStatus::UpdateAvailable
$result->mustUpdate();    // false

$downloads = ReleaseKit::downloads(channel: 'stable');                  // DownloadsResult, every platform
$windows   = ReleaseKit::downloadsFor('windows', architecture: 'x64');  // PlatformDownloads
$history   = ReleaseKit::releases(platform: 'android')->paginate(20);
```

The facade does not check channel access: your code decides who may see protected channels.

## Protected channels

```php
// config/releasekit.php
'channels' => [
    'stable' => ['access' => 'public'],
    'beta' => ['access' => 'protected', 'disk' => 'private-builds'],
],
'access' => ['ability' => 'releasekit.access-channel', 'guard' => 'sanctum'],
```

```php
// AppServiceProvider::boot()
Gate::define('releasekit.access-channel', fn (?User $user, string $channel) => $user?->isBetaTester() ?? false);
```

- `check`, `downloads` and `releases` for a protected channel ask the Gate (with the user from the configured guard; no auth middleware needed). No access → `403`. An undefined ability denies access.
- Artifact URLs on protected channels are always **temporary signed URLs** (valid for `delivery.signed_url_ttl` seconds). They work without a session, e.g. in a browser or download manager.
- Protected channels never use `public` delivery. If your storage makes whole buckets public (Cloudflare R2), keep protected channels on a separate private disk.

## File storage and delivery

**Uploads.** `AttachArtifact` accepts an `UploadedFile` (streamed, never read into memory) or a **path that already exists on the disk**. For large builds, upload straight to storage (Livewire S3 temporary uploads, presigned PUT URLs) and pass the path; the file is moved into place with a server-side copy. Files go to `{directory}/{channel}/{platform}/{version}/{ulid}-{filename}` on the channel's disk, else `storage.disk`, else the default disk. `storage.max_upload_size_mb` (default 2048) applies to both. A queued job computes `checksum_sha256`. 1 GB+ files are covered by the test suite.

**Delivery** (`delivery.mode`):

| Mode | How | Counts downloads | Load on app |
|---|---|---|---|
| `stream` | The app streams the file | yes | high: a worker per download |
| `signed` (default) | 302 to a temporary storage URL | yes | low |
| `public` + `public_through_app: true` | 302 to the public storage/CDN URL | yes | very low |
| `public` + `public_through_app: false` | The API returns the storage/CDN URL directly | no | none |

If the disk can't do what the mode needs, it falls back (`public` → `signed` → `stream`) and logs a warning once.

**Clean-up.** Deleting an artifact queues its file for deletion after commit. `php artisan releasekit:prune-orphans --dry-run` lists files under the ReleaseKit directory with no artifact row; drop `--dry-run` to delete them (files changed in the last 60 minutes are skipped).

**Caching.** The per platform + channel state is cached, so a warm version check runs **zero queries**. Model observers clear it on every write; signed URLs are generated per request and never cached.

## Analytics

Off by default. To enable:

```bash
php artisan vendor:publish --tag=releasekit-analytics-migrations
php artisan migrate
```

```php
'analytics' => ['enabled' => true, 'track_checks' => true, 'track_downloads' => true, 'country_header' => 'CF-IPCountry'],
```

Checks (by client version) and downloads (by artifact) through the API are counted per day. **No IPs, user agents or device IDs are stored.** Query with the `Stat` model:

```php
use TecClub\ReleaseKit\Enums\StatEvent;
use TecClub\ReleaseKit\Models\Stat;

Stat::event(StatEvent::Check)->forPlatform('android')->between(now()->subDays(30), now())->groupByVersion()->get();
Stat::event(StatEvent::Download)->forArtifact($artifact)->sum('count');
```

A recorded download is an initiated download, not a completed one. Downloads served straight from storage (`public_through_app: false`) can't be counted. Without the table, analytics does nothing and everything else keeps working.

## Client apps

Give app teams [`docs/client-integration.md`](docs/client-integration.md): when to call `check`, what to send (the version name without the `+build` part), how to handle each status, and to **fail open** if the server can't be reached. It includes a Flutter example.

## Laravel Boost

ReleaseKit ships a [Laravel Boost](https://github.com/laravel/boost) guideline (`resources/boost/guidelines/core.blade.php`) and a `releasekit` skill (`resources/boost/skills/releasekit`). They are picked up by `php artisan boost:install` / `boost:update` in host projects, so AI agents use the Actions, never compare versions by hand, and register routes correctly.

## Testing

```bash
composer test          # the suite (SQLite)
composer test-large    # 1 GB+ file handling
composer analyse       # PHPStan
composer format        # Pint
```

CI runs the suite on PHP 8.3–8.5 with Laravel 12 and 13, and against MySQL and PostgreSQL.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).

Built by [TecClub](https://tecclubx.com).
