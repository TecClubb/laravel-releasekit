# ReleaseKit configuration

File: `config/releasekit.php` (publish with `php artisan vendor:publish --tag=releasekit-config`). Env prefix: `RELEASEKIT_`.

Platforms, architectures, formats, stores and channels are stored as plain strings: adding one is a config change, never a migration. If a key is removed from config while rows still use it, those rows still load and render with the raw key as label; new writes are validated against config.

## channels / default_channel

```php
'channels' => [
    'stable' => ['access' => 'public'],
    'beta' => ['access' => 'protected', 'disk' => 'private', 'label' => 'Beta'],
],
'default_channel' => 'stable',
```

| Key | Effect |
|---|---|
| `access` | `public`: open to anyone. `protected`: `check`, `downloads`, `releases` require the Gate ability (`access.ability`) and artifact URLs are always temporary signed URLs. Unknown or invalid values are treated as protected. |
| `disk` | Optional. Overrides `storage.disk` for this channel's files. |
| `label` | Optional display label; defaults to the key. |

## platforms / stores

```php
'platforms' => [
    'android' => [
        'label' => 'Android',
        'architectures' => ['arm64-v8a', 'armeabi-v7a', 'x86_64'],
        'formats' => ['apk', 'aab'],
        'downloadable_formats' => ['apk'],   // only these are offered by the API
        'stores' => ['play_store'],          // keys from 'stores'
    ],
    // ios, macos, windows, linux ship by default
],
'stores' => [
    'play_store' => ['label' => 'Google Play'],
    // app_store, testflight, mac_app_store, microsoft_store, snap, flathub
],
```

`downloadable_formats` is always intersected with `formats`. A store link needs the store in both `stores` and the platform's `stores`.

## storage

| Key | Default | Effect |
|---|---|---|
| `storage.disk` | `env('RELEASEKIT_DISK')` (null) | Disk for artifacts. Resolution: channel `disk` → this → `filesystems.default`. |
| `storage.directory` | `env('RELEASEKIT_DIRECTORY', 'releasekit')` | Root folder; files go to `{directory}/{channel}/{platform}/{version}/{ulid}-{filename}`. `releasekit:prune-orphans` only scans here. |
| `storage.max_upload_size_mb` | `2048` | Enforced by `AttachArtifact` for uploads and existing paths. `0` disables the limit. |

## delivery

| Key | Default | Effect |
|---|---|---|
| `delivery.mode` | `env('RELEASEKIT_DELIVERY', 'signed')` | `stream`: the app streams the file. `signed`: 302 to a temporary storage URL. `public`: 302 to the public storage URL. Protected channels never use `public` (they use `signed`). Fallbacks: `public` → `signed` when the disk has no public URL; `signed` → `stream` when it can't sign (logged once). |
| `delivery.signed_url_ttl` | `300` | Seconds for temporary storage URLs and signed route URLs. |
| `delivery.public_through_app` | `true` | `public` mode only. `true`: the API returns the app's download URL, which counts the download and redirects to storage. `false`: the API returns the storage/CDN URL directly; downloads can't be counted. |

A local disk counts as having public URLs only when its config has a `url`.

## routes

| Key | Default | Effect |
|---|---|---|
| `routes.register` | `false` | `true`: the package registers its routes. `false`: publish `routes/releasekit.php` (`--tag=releasekit-routes`) or write your own. |
| `routes.prefix` | `api/releasekit` | URI prefix (auto-registration only). |
| `routes.name` | `releasekit.` | Route name prefix, used by auto-registration **and** read by the published stub (`Route::name(config('releasekit.routes.name'))`). Download URLs use `{name}artifacts.download`. Don't add another `->name()` prefix around the stub. |
| `routes.middleware` | `['api', 'throttle:120,1']` | Middleware (auto-registration only). The published stub adds none: wrap its include with your own throttle. |
| `routes.endpoints.check` / `downloads` / `releases` / `artifact_download` | `true` | Turn individual endpoints off (auto-registration only). |

## access

| Key | Default | Effect |
|---|---|---|
| `access.ability` | `releasekit.access-channel` | Gate ability for protected channels: `fn (?Authenticatable $user, string $channel): bool`. Undefined ability = denied. |
| `access.guard` | `null` | Guard the user is resolved from (e.g. `sanctum`), without requiring auth middleware. `null` = default guard. |

## cache

| Key | Default | Effect |
|---|---|---|
| `cache.enabled` | `true` | Caches the per platform + channel state, so a version check runs zero queries. Cleared automatically by model observers. Set `false` in tests if needed. |
| `cache.store` | `null` | Cache store; `null` = default. |
| `cache.ttl` | `300` | Seconds; only a safety net, since writes clear the cache. |

## tables / models

```php
'tables' => ['releases' => 'app_releases', 'tracks' => 'app_release_tracks', 'artifacts' => 'app_release_artifacts', 'links' => 'app_release_links', 'stats' => 'app_release_stats'],
'models' => ['release' => Release::class, 'track' => Track::class, 'artifact' => Artifact::class, 'link' => Link::class, 'stat' => Stat::class],
```

Extend a package model and register your class here; every relationship, service and observer resolves models from this config.

## analytics

| Key | Default | Effect |
|---|---|---|
| `analytics.enabled` | `false` | Records daily aggregate counts. Also requires the published migration (`--tag=releasekit-analytics-migrations`); without the table it does nothing. |
| `analytics.track_checks` | `true` | Count `GET check` calls (by client version, architecture, country). |
| `analytics.track_downloads` | `true` | Count downloads through the app's download endpoint (by artifact). |
| `analytics.country_header` | `CF-IPCountry` | Header holding a two-letter country; `null` disables country. |

No IPs, user agents or device IDs are stored. Query with `TecClub\ReleaseKit\Models\Stat` scopes: `between`, `event`, `forPlatform`, `forChannel`, `forArtifact`, `groupByVersion`, `groupByDate`, `groupByCountry`, `groupByPlatform` (grouping scopes select the column plus `total`).

## Migrations

The core migrations load automatically. To manage them yourself: `php artisan vendor:publish --tag=releasekit-migrations`, then call `TecClub\ReleaseKit\ReleaseKit::ignoreMigrations()` in a service provider's `register()`.

## Commands

`php artisan releasekit:prune-orphans [--dry-run] [--disk=*] [--older-than=60] [--force]` lists or deletes files under `storage.directory` that no artifact row references. Files modified in the last `--older-than` minutes are skipped.
