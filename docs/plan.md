# ReleaseKit — Laravel Package Plan

A headless Laravel package for managing app releases, build artifacts, downloads, and version checks across platforms.

- **Package:** `tecclub/releasekit`
- **Namespace:** `TecClub\ReleaseKit`
- **Config key:** `releasekit`
- **Env prefix:** `RELEASEKIT_`
- **Type:** Standalone Laravel package (its own repository, tested with Orchestra Testbench). One app per host project.
- **Status:** Planning. No code has been written yet.

---

## 1. Scope

**The package provides:**

- Database tables and models for releases, tracks, artifacts, and links.
- Actions for every write operation: create, publish, promote, yank, attach artifact, and so on. Host admin panels call these.
- Services for version checks, download resolution, and file delivery. They can be used over HTTP or directly in PHP.
- Controllers for the public API. **Routes are not registered by default.**
- File delivery in three modes: streaming, signed URLs, or public URLs.
- Public or protected access per channel.
- Optional analytics, disabled by default.
- A Laravel Boost guideline and agent skill, so AI agents in host projects use the package correctly.

**The package does not provide:**

- An admin UI. Each host builds its own, on top of the Actions.
- Auth, users, permissions, or tenancy.
- An apps table, because each host project has one app.

### Initial platforms

`android`, `ios`, `macos`, `windows`, `linux`. More can be added through config only.

---

## 2. Goals

1. Manage releases per platform and channel: version, build number, notes, status.
2. Attach one or more build files (artifacts) to each release, optionally per architecture.
3. Manage store and external links, either per release or permanently per platform.
4. Give each platform and channel an explicit **current release** and a **minimum supported version**.
5. Apps call a **version check** to learn whether they are up to date, can update, must update, or are blocked.
6. Websites call a **downloads** endpoint for the current artifacts and links, and optionally a **release history** endpoint.
7. Adding a platform, architecture, format, or store is a **config change, not a migration**.
8. **Analytics are optional and disabled by default.** Nothing in the core depends on them.

---

## 3. Key decisions

| Decision | Choice | Reason |
|---|---|---|
| UI | **Headless** | Host admin panels differ. The Actions are the integration surface |
| Platforms, architectures, formats, stores, channels | **Config**, stored as `VARCHAR` | New values need no migration. Never use DB `ENUM` columns |
| Lifecycle values (release status, link type) | **PHP enums**, stored as `VARCHAR` | The package's own logic depends on them |
| Versions vs releases | One table, `app_releases`, one row per platform + channel + version | Almost all data differs per platform |
| "Current" release | Explicit, via `app_release_tracks` | Publishing does not equal going live. Rollback means pointing the track at another release |
| Force update | `minimum_supported_version` on the track | One clear threshold per platform and channel |
| Comparison | **Version strings in PHP**, with a strict format | Nothing is ordered in SQL, because the track picks "current" explicitly |
| Build number | Stored on the release, nullable, **display only** | Useful for support, not used in logic |
| Architecture | Column on the artifact, `null` = universal | No separate variants table |
| Links | `app_release_id` nullable, plus a `platform` column | Permanent store links per platform, release-specific links where needed |
| Routes | **Not auto-registered by default.** Opt-in through config, or publish a route stub | Hosts register routes their own way (for example with an API versioning package) |
| Delivery | Config: `stream`, `signed`, or `public` (public served directly or through an app redirect) | No schema impact |
| Access | Per channel: `public` or `protected` | For example, stable is public and beta needs auth |
| Analytics | Daily aggregate counts in an optional table | Raw per-check events don't scale. Stores no IPs, user agents, or device IDs |

### Team rule this design depends on

> **Any build that reaches users gets a new version string.**
> A hotfix shipped as 1.2.0+45 → 1.2.0+46 must be released as **1.2.1**. Otherwise clients on both builds report `1.2.0` and can't be told apart.

### Version format

- Validation regex: `^\d+(\.\d+){1,3}$`. Examples: `1.2`, `1.2.0`, `1.2.0.4`.
- No pre-release suffixes like `-beta`. Use **channels** for those builds instead.
- All comparisons go through `TecClub\ReleaseKit\Support\VersionComparator`, which wraps `version_compare`. No other code compares versions directly.

---

## 4. Configuration

`config/releasekit.php`

```php
return [

    /*
    | Channels. "access" is public or protected.
    | "disk" optionally overrides storage.disk for this channel
    | (e.g. keep beta builds in a private bucket).
    */
    'channels' => [
        'stable' => ['access' => 'public'],
        'beta'   => ['access' => 'protected'],
    ],
    'default_channel' => 'stable',

    'platforms' => [
        'android' => [
            'label'                => 'Android',
            'architectures'        => ['arm64-v8a', 'armeabi-v7a', 'x86_64'],
            'formats'              => ['apk', 'aab'],
            'downloadable_formats' => ['apk'], // aab is stored for archive, never offered
            'stores'               => ['play_store'],
        ],
        'ios' => [
            'label'                => 'iOS',
            'architectures'        => [],
            'formats'              => ['ipa'],
            'downloadable_formats' => [],
            'stores'               => ['app_store', 'testflight'],
        ],
        'macos' => [
            'label'                => 'macOS',
            'architectures'        => ['arm64', 'x64'],
            'formats'              => ['dmg', 'pkg', 'zip'],
            'downloadable_formats' => ['dmg', 'pkg', 'zip'],
            'stores'               => ['mac_app_store'],
        ],
        'windows' => [
            'label'                => 'Windows',
            'architectures'        => ['x64', 'arm64', 'x86'],
            'formats'              => ['exe', 'msi', 'msix', 'zip'],
            'downloadable_formats' => ['exe', 'msi', 'msix', 'zip'],
            'stores'               => ['microsoft_store'],
        ],
        'linux' => [
            'label'                => 'Linux',
            'architectures'        => ['x64', 'arm64'],
            'formats'              => ['appimage', 'deb', 'rpm', 'tar.gz'],
            'downloadable_formats' => ['appimage', 'deb', 'rpm', 'tar.gz'],
            'stores'               => ['snap', 'flathub'],
        ],
    ],

    'stores' => [
        'play_store'      => ['label' => 'Google Play'],
        'app_store'       => ['label' => 'App Store'],
        'testflight'      => ['label' => 'TestFlight'],
        'mac_app_store'   => ['label' => 'Mac App Store'],
        'microsoft_store' => ['label' => 'Microsoft Store'],
        'snap'            => ['label' => 'Snap Store'],
        'flathub'         => ['label' => 'Flathub'],
    ],

    'storage' => [
        'disk'               => env('RELEASEKIT_DISK'),      // null = config('filesystems.default')
        'directory'          => env('RELEASEKIT_DIRECTORY', 'releasekit'),
        'max_upload_size_mb' => 2048,
    ],

    'delivery' => [
        'mode'           => env('RELEASEKIT_DELIVERY', 'signed'), // stream | signed | public
        'signed_url_ttl' => 300, // seconds, for storage temporary URLs and signed routes

        // Only for "public" mode.
        // true  = the API returns the app's download URL, which 302-redirects to the public storage URL
        //         (downloads are counted, and links stay stable if the storage/CDN changes)
        // false = the API returns the public storage URL directly (no download analytics)
        'public_through_app' => true,
    ],

    'routes' => [
        'register'   => false, // true = package registers its routes. false = use the published stub or your own
        'prefix'     => 'api/releasekit',
        'name'       => 'releasekit.',
        'middleware' => ['api', 'throttle:120,1'],
        'endpoints'  => [
            'check'             => true,
            'downloads'         => true,
            'releases'          => true, // history / changelog
            'artifact_download' => true,
        ],
    ],

    'access' => [
        // Gate ability checked for protected channels: fn (?Authenticatable $user, string $channel): bool
        'ability' => 'releasekit.access-channel',
        'guard'   => null, // null = default guard. e.g. 'sanctum'
    ],

    'cache' => [
        'enabled' => true,
        'store'   => null, // null = default store
        'ttl'     => 300,  // seconds. Cleared automatically on changes
    ],

    'tables' => [
        'releases'  => 'app_releases',
        'tracks'    => 'app_release_tracks',
        'artifacts' => 'app_release_artifacts',
        'links'     => 'app_release_links',
        'stats'     => 'app_release_stats',
    ],

    'models' => [
        'release'  => \TecClub\ReleaseKit\Models\Release::class,
        'track'    => \TecClub\ReleaseKit\Models\Track::class,
        'artifact' => \TecClub\ReleaseKit\Models\Artifact::class,
        'link'     => \TecClub\ReleaseKit\Models\Link::class,
        'stat'     => \TecClub\ReleaseKit\Models\Stat::class,
    ],

    'analytics' => [
        'enabled'         => false,
        'track_checks'    => true,
        'track_downloads' => true,
        'country_header'  => 'CF-IPCountry', // null to disable country
    ],
];
```

**Disk resolution order:** the channel's `disk`, then `storage.disk`, then `config('filesystems.default')`.

**Unknown values in the database.** If a platform or channel is removed from config while rows still use it, those rows still load and render using the raw key as the label. New writes are validated against config.

---

## 5. Database schema

There are four core tables and one optional table. Every table name comes from `config('releasekit.tables.*')`.

### 5.1 `app_releases`

One row per platform + channel + version.

| Column | Type | Notes |
|---|---|---|
| `id` | `bigIncrements` | |
| `platform` | `string(50)` | Validated against config |
| `channel` | `string(50)`, default `stable` | Validated against config |
| `version` | `string(50)` | Strict format, see §3 |
| `build_number` | `string(50)`, nullable | Display only |
| `status` | `string(20)`, default `draft` | `draft` / `published` / `yanked` |
| `release_notes` | `text`, nullable | Markdown, single language |
| `metadata` | `json`, nullable | |
| `published_at` | `timestamp`, nullable | Set when first published |
| `yanked_at` | `timestamp`, nullable | |
| `yank_reason` | `string(255)`, nullable | Internal, never exposed by the API |
| timestamps | | |

- Unique: `(platform, channel, version)`
- Index: `(platform, channel, status)`

### 5.2 `app_release_tracks`

One row per platform + channel. It decides what clients receive.

| Column | Type | Notes |
|---|---|---|
| `id` | `bigIncrements` | |
| `platform` | `string(50)` | |
| `channel` | `string(50)` | |
| `current_release_id` | `foreignId`, nullable | → `app_releases.id`, **restrict on delete** |
| `minimum_supported_version` | `string(50)`, nullable | Same format rules as `version` |
| timestamps | | |

- Unique: `(platform, channel)`

Tracks are created automatically the first time a release is published for that platform and channel. Hosts can also create them ahead of time.

### 5.3 `app_release_artifacts`

Uploaded build files. The bytes live on the filesystem disk.

| Column | Type | Notes |
|---|---|---|
| `id` | `bigIncrements` | |
| `app_release_id` | `foreignId` | → `app_releases.id`, **cascade on delete** |
| `architecture` | `string(50)`, nullable | `null` = universal |
| `format` | `string(20)` | Validated against the platform's `formats` |
| `label` | `string(100)`, nullable | e.g. "Windows Installer (MSI)" |
| `disk` | `string(64)` | Resolved disk at upload time |
| `path` | `string(500)` | Storage key. Never exposed except in `public` delivery mode |
| `original_filename` | `string(255)` | Used for `Content-Disposition` |
| `mime_type` | `string(150)`, nullable | |
| `size_bytes` | `unsignedBigInteger`, nullable | |
| `checksum_sha256` | `char(64)`, nullable | Filled by a queued job |
| `is_active` | `boolean`, default `true` | |
| `sort_order` | `unsignedSmallInteger`, default `0` | Lowest value = primary |
| `metadata` | `json`, nullable | |
| timestamps | | |

- Index: `(app_release_id, is_active)`

**One artifact owns one file.** Files are never shared between artifacts. Storage path: `{directory}/{channel}/{platform}/{version}/{ulid}-{original_filename}`.

### 5.4 `app_release_links`

Store listings and external URLs.

| Column | Type | Notes |
|---|---|---|
| `id` | `bigIncrements` | |
| `platform` | `string(50)` | Copied from the release when `app_release_id` is set |
| `app_release_id` | `foreignId`, **nullable** | `null` = permanent platform link. **Cascade on delete** |
| `type` | `string(20)` | `store` / `external` |
| `store` | `string(50)`, nullable | Key from `config('releasekit.stores')`. Required when `type = store` |
| `label` | `string(100)`, nullable | Falls back to the store label |
| `url` | `string(2048)` | |
| `is_active` | `boolean`, default `true` | |
| `sort_order` | `unsignedSmallInteger`, default `0` | Lowest value = primary |
| timestamps | | |

- Index: `(platform, app_release_id, is_active)`

### 5.5 `app_release_stats` (optional, publishable migration)

Daily aggregate counts. **No IPs, user agents, or device IDs are stored.**

| Column | Type | Notes |
|---|---|---|
| `id` | `bigIncrements` | |
| `date` | `date` | |
| `event` | `string(20)` | `check` / `download` |
| `platform` | `string(50)` | |
| `channel` | `string(50)` | |
| `version` | `string(50)`, default `''` | The client version for checks, the release version for downloads |
| `architecture` | `string(50)`, default `''` | |
| `artifact_id` | `unsignedBigInteger`, default `0` | No FK, so stats survive deletion |
| `country` | `char(2)`, default `''` | |
| `count` | `unsignedBigInteger`, default `0` | |

- Unique: `(date, event, platform, channel, version, architecture, artifact_id, country)`
- Writes are atomic upserts that increment `count`.
- Dimension columns use `''` and `0` instead of `NULL`, because a unique index doesn't treat NULLs as equal and the upsert would create duplicate rows.

Hosts that want analytics publish this migration with `php artisan vendor:publish --tag=releasekit-analytics-migrations`. It is never loaded conditionally at runtime.

---

## 6. Domain rules

### 6.1 Release status

| Status | Visible via API | Can be current | Editable | Deletable |
|---|---|---|---|---|
| `draft` | No | No | Fully | Yes |
| `published` | Yes | Yes | Notes, links, artifacts. Not version, platform, or channel | No |
| `yanked` | No downloads. Used for blocking | No | Notes only | No |

Transitions:
- `draft → published`
- `published → yanked`
- `yanked → published` (un-yank)

Rules:
- A release that is **current on a track cannot be yanked** unless a rollback target is passed. `YankRelease` then moves the track and yanks the release in one transaction.
- Published and yanked releases are **never deleted**. Yanked rows are what make blocking work.

### 6.2 Track rules

These are enforced in the Actions, inside a DB transaction with a row lock on the track.

- `current_release_id` must point to a `published` release with the **same platform and channel**.
- `minimum_supported_version` must be `<=` the current release's version.
- `PromoteRelease::warnings($release)` returns readiness warnings without blocking. For example, the release lacks an architecture or format that the current release has. Host UIs can show these before confirming.

### 6.3 Link and artifact rules

- `store` must exist in config and be allowed for the platform.
- `format` must be in the platform's `formats`. `architecture`, if set, must be in the platform's `architectures`.
- Only formats in `downloadable_formats` are returned by the API.

### 6.4 Enforcement

Rules live in the Actions, and hosts should write through them. The database enforces what it can: uniqueness, foreign keys, and restricted deletes. Cache clearing happens in **model observers**, not in the Actions, so the cache stays correct even if a host writes to the models directly.

---

## 7. Public API

### 7.1 Route registration

There are two ways to expose the API:

**A. Auto-register.** Set `routes.register = true`. The package registers the enabled `endpoints` under `routes.prefix`, `routes.name`, and `routes.middleware`.

**B. Register yourself (default).** Publish the route stub and include it however the host registers API routes, including inside an API versioning package's structure.

```bash
php artisan vendor:publish --tag=releasekit-routes        # routes/releasekit.php
php artisan vendor:publish --tag=releasekit-controllers   # optional: copies controllers into app/Http/Controllers/ReleaseKit
```

The route stub uses plain `Route::` definitions that point at the package controllers:

```php
use TecClub\ReleaseKit\Http\Controllers\{CheckController, DownloadsController, ReleasesController, ArtifactDownloadController};

Route::get('check', CheckController::class)->name('releasekit.check');
Route::get('downloads', DownloadsController::class)->name('releasekit.downloads');
Route::get('releases', ReleasesController::class)->name('releasekit.releases');
Route::get('artifacts/{artifact}/download', ArtifactDownloadController::class)->name('releasekit.artifacts.download');
```

**Recommended: point the routes at the package controllers rather than copying them.** The controllers are thin: they validate input, call a service, and return a resource. If you copy them, you stop receiving bug fixes. Publish the controllers only when a project needs a different response shape.

**Artifact download URLs.** The package builds download URLs through a `DownloadUrlGenerator`. It uses the route name `config('releasekit.routes.name') . 'artifacts.download'`. If a host renames that route, it binds its own generator in the container.

### 7.2 Using the services directly (no HTTP)

When the website lives in the same Laravel app, it can call the services directly:

```php
use TecClub\ReleaseKit\Facades\ReleaseKit;

$result    = ReleaseKit::check(platform: 'android', version: '1.1.0', channel: 'stable', architecture: 'arm64-v8a');
$downloads = ReleaseKit::downloads(platform: null, channel: 'stable');   // all platforms
$history   = ReleaseKit::releases(platform: 'android', channel: 'stable')->paginate(20);
```

The HTTP controllers are thin wrappers around these same calls.

### 7.3 Version check

```
GET {prefix}/check?platform=android&channel=stable&version=1.1.0&architecture=arm64-v8a
```

| Param | Required | Notes |
|---|---|---|
| `platform` | yes | Must exist in config |
| `version` | yes | Strict format |
| `channel` | no | Defaults to `default_channel` |
| `architecture` | no | Filters artifacts |

**Algorithm.** Evaluated in this order; the first match wins.

1. The client's version matches a `yanked` release on this platform and channel → `blocked`
2. The client's version is below `minimum_supported_version` → `update_required`
3. The client's version is below the current release's version → `update_available`
4. Otherwise (including dev builds newer than current) → `up_to_date`

If there is no track or no current release, rule 2 still applies if a minimum is set. Otherwise the result is `up_to_date` with `current: null`.

**Response**

```json
{
  "status": "update_available",
  "client_version": "1.1.0",
  "minimum_supported_version": "1.0.0",
  "current": {
    "version": "1.2.0",
    "build_number": "45",
    "release_notes": "…",
    "published_at": "2026-10-20T10:00:00Z"
  },
  "downloads": {
    "artifacts": [
      {
        "id": 12,
        "architecture": "arm64-v8a",
        "format": "apk",
        "label": "APK (arm64)",
        "size_bytes": 48211234,
        "checksum_sha256": "…",
        "url": "…"
      }
    ],
    "links": [
      { "type": "store", "store": "play_store", "label": "Google Play", "url": "https://play.google.com/…" }
    ]
  }
}
```

`status` values: `up_to_date`, `update_available`, `update_required`, `blocked`.

### 7.4 Downloads (website)

```
GET {prefix}/downloads                    # all platforms
GET {prefix}/downloads?platform=windows
GET {prefix}/downloads?channel=beta       # protected channels need access, see §8
```

For each platform, return the track's current release, its downloadable active artifacts, its active release links, and the active platform-level links. Platforms with nothing to offer are omitted.

```json
{
  "channel": "stable",
  "platforms": {
    "windows": {
      "label": "Windows",
      "current": { "version": "1.1.3", "build_number": "42", "published_at": "…" },
      "artifacts": [ { "id": 31, "architecture": "x64", "format": "exe", "url": "…", "size_bytes": 0, "checksum_sha256": "…" } ],
      "links": [ { "type": "store", "store": "microsoft_store", "label": "Microsoft Store", "url": "…" } ]
    }
  }
}
```

**Architecture selection** (in both endpoints): if an `architecture` is requested, return exact matches plus universal (`null`) artifacts. Otherwise return everything. Order by `sort_order`.

### 7.5 Release history

```
GET {prefix}/releases?platform=android&channel=stable&page=1
```

Returns paginated `published` releases ordered by `published_at` descending: version, build_number, release_notes, and published_at. This endpoint is available but optional. Disable it in `routes.endpoints` or leave it out of your route file if a project doesn't need it.

### 7.6 Artifact download

```
GET {prefix}/artifacts/{artifact}/download
```

- Returns `404` unless the artifact is active, its format is downloadable, and its release is `published`.
- Delivers the file according to the delivery mode (§9).
- For protected channels, requires a valid signed URL (§8).
- Records a `download` stat when analytics is enabled. A recorded download is an *initiated* download, not a confirmed one.

### 7.7 Errors

- `422` for an unknown platform or channel, or an invalid version format, using standard Laravel validation errors.
- `403` when a protected channel is requested without access.
- `404` for a missing or unavailable artifact.
- `403` for an invalid or expired signature on a protected download.

---

## 8. Access control (public vs protected channels)

Each channel is set to `access: public` or `access: protected` in config.

**Public channels**

- `check`, `downloads`, and `releases` are open to anyone.
- Artifact URLs follow the delivery mode as normal.

**Protected channels**

- `check`, `downloads`, and `releases` call `Gate::forUser($request->user($guard))->allows($ability, $channel)`. The host defines the ability:

  ```php
  Gate::define('releasekit.access-channel', function (?User $user, string $channel) {
      return $user?->isBetaTester() ?? false;
  });
  ```

  The user is resolved from the configured guard (such as `sanctum`) without requiring auth middleware. That way the same routes serve both public and protected channels.
- Artifact URLs are **always temporary signed route URLs** (`URL::temporarySignedRoute`, valid for `signed_url_ttl`). The download endpoint verifies the signature. The caller is authorized when the list of downloads is requested, and the signed link then works without a session, for example in a browser or download manager.
- The `public` delivery mode is **never used** for protected channels. They fall back to `signed`.
- **Storage caveat:** some providers, Cloudflare R2 among them, make a whole bucket public rather than individual objects. If public channels use `public` delivery, put protected channels on a separate private disk by setting `channels.beta.disk`.

---

## 9. Delivery modes

Delivery is set globally with `delivery.mode`. Protected channels can't use `public` (§8).

| Mode | How it works | Analytics | Access control | Load on app |
|---|---|---|---|---|
| `stream` | The download endpoint streams the file from the disk (`readStream`) with the correct MIME type and `Content-Disposition`. It is never loaded fully into memory | Yes | Yes | High. A PHP worker is busy for the whole download |
| `signed` (default) | The download endpoint redirects with `302` to a temporary storage URL (`temporaryUrl`) that expires after `signed_url_ttl`, with `Content-Disposition` set to the original filename | Yes | Yes | Low |
| `public` + `public_through_app = true` (default for public) | The API returns the app's download URL. The endpoint records the stat and redirects with `302` to the public storage URL (`Storage::url`). The bytes come from storage or the CDN | **Yes** | **No**, the file URL itself is public | Very low. One tiny request per download, with no signing and no streaming |
| `public` + `public_through_app = false` | The API returns the public storage URL **directly** | **No**. Downloads bypass the app | **No** | None |

**Why "through app" and not counting when the API returns the URL.** The `downloads` endpoint returns every platform's links on each page view, and the website may cache that response server-side. Counting there would measure page views multiplied by the number of platforms, not downloads. The only point that reliably maps to one download is the request for the file itself, so in public mode that request has to pass through the app once.

`public_through_app = true` has a second benefit: download links that have already been shared (in emails, forums, QR codes) keep working if the bucket, domain, or CDN changes later.

Set it to `false` only when you specifically need raw CDN URLs in API responses and don't need download counts. In that case, the CDN or storage provider's own analytics (for example Cloudflare's analytics on an R2 custom domain) is where download numbers come from.

**Fallbacks**

- If `signed` is configured but the disk can't create temporary URLs → `stream`, with a warning logged once.
- If `public` is configured but the disk has no public URL → `signed`, and failing that `stream`.

---

## 10. Caching

**Why it's needed.** Every launch of every app instance calls `check`. Without a cache, each call costs several queries: the track, the current release, artifacts, links, and the list of yanked versions. With a cache, a check is **zero queries** and a few comparisons in PHP.

**What is cached**, per `(platform, channel)`:
- the current release payload
- the minimum supported version
- the yanked versions
- artifacts and links

The client-specific part (comparing the client's version against these values) runs on every request.

**Invalidation.** Model observers on releases, tracks, artifacts, and links clear the affected `(platform, channel)` keys on save and delete. The TTL is only a safety net. Signed artifact URLs are generated **after** the cache read, never cached, so they don't expire inside the cache.

Set `cache.enabled = false` to turn it off, for example in tests.

---

## 11. Storage

- The disk is resolved as described in §4. Files go to `{directory}/{channel}/{platform}/{version}/{ulid}-{original_filename}`. The default directory is `releasekit`, because it matches the package name and clearly marks which part of a shared bucket the package owns.
- `AttachArtifact` accepts either:
  - an `UploadedFile`, which is streamed to the disk and not read into memory, or
  - an **existing path** on the disk, for files the host already uploaded directly to storage. For large builds this is the recommended route, for example through Livewire's S3 temporary uploads or presigned PUT URLs. The package moves the file to its final path, which is a server-side copy on S3 and R2.
- `max_upload_size_mb` is enforced for both inputs.
- After an artifact is created, a queued job `ComputeArtifactChecksum` streams the object and fills in `size_bytes`, `mime_type`, and `checksum_sha256` if they're missing.
- **Deletion:** a queued `DeleteArtifactFile` job runs **after the DB commit**. A failed file delete only logs a warning; it never blocks the DB operation.
- `php artisan releasekit:prune-orphans --dry-run` lists files under the directory that have no artifact row.

---

## 12. Deletion policy

| Operation | Behavior |
|---|---|
| Delete a draft release | Allowed. Artifacts and links cascade, and their files are queued for deletion |
| Delete a published or yanked release | Blocked by the Action. Use yank instead |
| Delete a release that is current on a track | Blocked by the FK (restrict) and the Action |
| Delete an artifact | Allowed. File deletion queued after commit |
| Delete a link | Allowed |
| Release deleted → stats | Unaffected (no FK) |

---

## 13. Host integration (headless usage)

### Install

```bash
composer require tecclub/releasekit
php artisan vendor:publish --tag=releasekit-config
php artisan migrate
php artisan vendor:publish --tag=releasekit-routes      # unless routes.register = true

# optional
php artisan vendor:publish --tag=releasekit-analytics-migrations && php artisan migrate
```

### Building an admin panel on the Actions

```php
use TecClub\ReleaseKit\Actions\{CreateRelease, AttachArtifact, AddLink, PublishRelease, PromoteRelease, YankRelease, UpdateTrackMinimum};

$release = app(CreateRelease::class)->handle(
    platform: 'android', version: '1.2.0', channel: 'stable',
    buildNumber: '45', releaseNotes: '…',
);

app(AttachArtifact::class)->handle($release, $uploadedFileOrPath, format: 'apk', architecture: 'arm64-v8a');
app(AddLink::class)->handle(platform: 'android', store: 'play_store', url: '…');          // permanent platform link
app(PublishRelease::class)->handle($release);

$warnings = app(PromoteRelease::class)->warnings($release);  // show these in the UI before confirming
app(PromoteRelease::class)->handle($release);

app(YankRelease::class)->handle($release, reason: 'Crash on launch', rollbackTo: $previous);
app(UpdateTrackMinimum::class)->handle(platform: 'android', channel: 'stable', version: '1.1.0');
```

Actions throw a `ReleaseKitException` subclass with a readable message when a rule is broken. Hosts catch it and show the message in their UI.

**Read side.** Hosts query the models directly for their admin lists. The models include scopes such as `forPlatform`, `forChannel`, `published`, `drafts`, and `yanked`.

### Events

`ReleaseCreated`, `ReleasePublished`, `ReleasePromoted`, `ReleaseYanked`, `TrackMinimumUpdated`, `ArtifactAttached`, `ArtifactDownloaded`.

Hosts listen to these for notifications, audit logs, and similar hooks.

### Extending models

Hosts extend the package models and register their own classes in `releasekit.models.*`. All relationships and services resolve models from config.

---

## 14. Package structure

```
tecclub/releasekit/
├── composer.json
├── config/releasekit.php
├── database/migrations/
│   ├── create_app_releases_table.php
│   ├── create_app_release_tracks_table.php
│   ├── create_app_release_artifacts_table.php
│   ├── create_app_release_links_table.php
│   └── analytics/create_app_release_stats_table.php     # publishable only
├── routes/releasekit.php                                # used for auto-registration and as the publish stub
├── stubs/controllers/                                   # optional publishable controllers
├── src/
│   ├── ReleaseKitServiceProvider.php                    # spatie/laravel-package-tools
│   ├── ReleaseKit.php + Facades/ReleaseKit.php
│   ├── Enums/{ReleaseStatus, LinkType, DeliveryMode, ChannelAccess}.php
│   ├── Models/{Release, Track, Artifact, Link, Stat}.php
│   ├── Observers/CacheInvalidationObserver.php
│   ├── Support/{PlatformRegistry, ChannelRegistry, VersionComparator, DiskResolver}.php
│   ├── Actions/{CreateRelease, UpdateRelease, PublishRelease, PromoteRelease, YankRelease,
│   │            UnyankRelease, DeleteDraftRelease, UpdateTrackMinimum,
│   │            AttachArtifact, UpdateArtifact, DeleteArtifact, AddLink, UpdateLink, DeleteLink}.php
│   ├── Services/{VersionChecker, DownloadResolver, ArtifactDelivery, DownloadUrlGenerator,
│   │             ChannelAccessGuard, ReleaseCache, StatsRecorder}.php
│   ├── Http/Controllers/{CheckController, DownloadsController, ReleasesController, ArtifactDownloadController}.php
│   ├── Http/Requests/...
│   ├── Http/Resources/...
│   ├── Events/...
│   ├── Exceptions/...
│   ├── Jobs/{ComputeArtifactChecksum, DeleteArtifactFile}.php
│   └── Console/PruneOrphansCommand.php
├── resources/boost/
│   ├── guidelines/core.blade.php                        # always-loaded rules (short)
│   └── skills/releasekit/
│       ├── SKILL.md                                     # on-demand task recipes
│       └── references/{actions.md, config.md, api.md, client-integration.md}
├── docs/client-integration.md                           # API contract for app teams (Flutter etc.)
├── tests/
└── README.md
```

`StatsRecorder` does nothing when analytics is disabled or the stats table doesn't exist.

---

## 15. Analytics (optional)

- Off by default (`analytics.enabled = false`). It needs the published migration.
- Records `check` events with the client version and `download` events with the artifact.
- In `public` delivery mode, downloads are recorded when `public_through_app = true` (the default). With `false`, downloads bypass the app and are not recorded.
- Country is read from a configurable header (by default `CF-IPCountry`) and is optional.
- Writes are atomic upserts. On MySQL, MariaDB, PostgreSQL, and SQLite they use each engine's upsert syntax.
- The package provides no reporting UI. Hosts query the `Stat` model, which includes scopes such as `between`, `event`, `forPlatform`, and `groupByVersion`.

---

## 16. Laravel Boost integration

**Goal.** When an AI agent works in a host project through Laravel Boost, it should know how to use ReleaseKit correctly without reading the package source. That covers building admin screens, adding a platform, registering routes, and similar tasks.

> **Verify before building.** Third-party package guidelines in Boost live at `resources/boost/guidelines/core.blade.php`, and Boost picks them up during `boost:install` / `boost:update`. Package-provided **skills** are newer. Confirm the current directory convention and frontmatter fields in the Boost docs for the version you target before writing them.

### 16.1 Two layers

| Layer | Loaded | Size | Purpose |
|---|---|---|---|
| Guideline (`guidelines/core.blade.php`) | Always, in every agent session | ~30 lines | The non-negotiable rules. Cheap enough to always be in context |
| Skill (`skills/releasekit/SKILL.md` + `references/`) | On demand, when a task matches the skill description | Longer | Step-by-step recipes and API details |

The guideline stays short on purpose. It costs context in every session, including sessions that never touch releases.

### 16.2 Guideline content (always loaded)

- ReleaseKit (`tecclub/releasekit`) manages app releases, artifacts, links, tracks, and version checks. It is headless: the host builds the UI.
- **Write only through Actions** (`TecClub\ReleaseKit\Actions\*`). Never create, update, or delete `Release`, `Track`, `Artifact`, or `Link` models directly. Reading through model scopes is fine.
- **Never compare versions manually.** Use `ReleaseKit::check()` or `VersionComparator`.
- Versions must match `^\d+(\.\d+){1,3}$`. No `-beta` suffixes; use channels.
- Platforms, architectures, formats, stores, and channels are **config**. Adding one is a config edit, never a migration or an enum.
- Published releases are never deleted. Use `YankRelease`, passing `rollbackTo` if the release is current.
- Large build files: upload directly to storage, then pass the path to `AttachArtifact`.
- Routes are **not** auto-registered. Check `releasekit.routes.register` or the published `routes/releasekit.php`.
- Catch `ReleaseKitException` and show its message to the admin.
- For tasks involving ReleaseKit, use the `releasekit` skill.

### 16.3 Skill content (on demand)

**Description (drives when the skill triggers):**
"Use when working with tecclub/releasekit: building admin screens for releases, artifacts, links, or tracks; adding platforms, architectures, or channels; registering or customizing the ReleaseKit API routes; setting up protected channels or delivery modes; building a website downloads or changelog page; enabling analytics; or integrating the version-check API in a client app."

**Recipes in `SKILL.md`:**

1. **Build admin screens.** Lists use model scopes. Writes use Actions. Show `PromoteRelease::warnings()` before confirming a promotion. Handle `ReleaseKitException`. The primary flow is draft → upload → publish → promote.
2. **Add a platform, architecture, format, or store.** Edit config only, and add the format to `downloadable_formats` if it should be offered publicly.
3. **Register routes.** Either auto-register or publish the stub. When the host uses an API versioning package, place the stub inside its structure and keep the route names, because signed download URLs depend on them.
4. **Set up a protected channel.** Channel config, the Gate ability, the guard, and a private disk if the storage provider makes buckets public as a whole (R2).
5. **Build a website downloads or changelog page.** Same app: use the facade. Separate frontend: use the HTTP endpoints.
6. **Enable analytics.** Publish the migration, set the flag, and query the `Stat` scopes.
7. **Integrate the version check in a client app.** Point to `references/client-integration.md`.

**References:**
- `actions.md`: every Action's signature, the rules it enforces, the exceptions it throws, and the events it fires.
- `config.md`: every config key with its default and effect.
- `api.md`: endpoints, parameters, response shapes, and error codes.
- `client-integration.md`: the client contract (below).

### 16.4 Client integration contract

Boost only runs in Laravel projects, so the Flutter or other client teams get the same contract as a plain document (`docs/client-integration.md`). It can also be dropped into the client repo as an agent skill. It covers:

- **When to call `check`:** on launch and on resume, after a minimum interval of a few hours.
- **What to send:** `platform`, `version` (the version name, for example `1.2.0` from `pubspec.yaml`, without the `+build` part), `channel`, and `architecture` (where it applies).
- **How to handle each status:**

  | Status | Client behavior |
  |---|---|
  | `blocked` | Full-screen, non-dismissible update screen |
  | `update_required` | Full-screen, non-dismissible update screen |
  | `update_available` | Dismissible prompt, at most once per version |
  | `up_to_date` | Nothing |

- **Fail open.** If the request fails or times out, the app keeps working normally. A server outage must never lock users out.
- **Update target.** Use the first store link for store builds. For sideloaded or desktop builds, use the first artifact for the client's architecture. Verify `checksum_sha256` when the app downloads an update itself.

### 16.5 Keeping it accurate

- Any PR that changes an Action signature, a config key, or the API response shape updates the guideline, skill, and references in the same PR.
- A test checks that every class and Action named in the guideline and in `actions.md` exists. This is a cheap guard against the docs drifting out of date.

---

## 17. Testing plan

Use Pest with Orchestra Testbench, running the suite against SQLite, MySQL, and PostgreSQL in CI.

**Must-have cases**

- `VersionComparator`: `1.10.0 > 1.9.0`, `1.2 == 1.2.0`, four-part versions, and rejection of invalid formats.
- `VersionChecker`, for each status:
  - yanked → `blocked`
  - below minimum → `update_required`
  - below current → `update_available`
  - equal → `up_to_date`
  - newer than current → `up_to_date`
  - no track → `up_to_date`
  - no current release but a minimum set → minimum still enforced
  - channels are isolated from each other
- Actions:
  - Promotion rejects drafts, yanked releases, and wrong platform or channel.
  - A minimum above current is rejected.
  - Yanking the current release requires a rollback target.
  - Published releases can't be deleted.
  - Promotion warnings are reported.
- `DownloadResolver`: exact architecture match plus universal; inactive and non-downloadable formats are excluded; platform links merge with release links.
- Delivery: each mode, the fallbacks when the disk lacks support, and `public` never used for protected channels.
- Access: protected channels return `403` without the gate; signed URLs are required and expire; public channels are open.
- Routes: nothing is registered by default; auto-register respects `endpoints`; the published stub works.
- Cache: cleared by model observers, including on direct model writes; signed URLs are never cached.
- Disk resolution: channel disk, then config disk, then system default.
- Adding a new platform in config works with no migration.
- With analytics disabled and no stats table, all endpoints still work.
- Public delivery: `public_through_app = true` returns app URLs, records the stat, and redirects to `Storage::url`. With `false`, the storage URL is returned directly and nothing is recorded.
- Boost: the guideline and skill files are present, and every class named in them exists.

---

## 18. Implementation phases

| Phase | Scope | Done when |
|---|---|---|
| 1. Skeleton | Repo, Testbench, config, migrations, models, enums, registries, `VersionComparator`, `DiskResolver` | Migrations run in Testbench, and comparator and registry tests pass |
| 2. Domain | All Actions, exceptions, events, observers | All domain-rule tests in §6 pass |
| 3. Services | `VersionChecker`, `DownloadResolver`, `ArtifactDelivery` (3 modes), `ChannelAccessGuard`, `ReleaseCache`, facade | Service tests pass without HTTP |
| 4. HTTP | Controllers, requests, resources, route stub, auto-registration, signed download URLs | API tests pass in both auto and published-route modes |
| 5. Storage | Upload handling for both inputs, checksum job, deletion job, prune command | A 1 GB+ file attaches without memory issues, and deletes clean up files |
| 6. Analytics | Publishable migration, `StatsRecorder`, `Stat` scopes | Disabled by default; counts are correct when enabled |
| 7. Docs & Boost | README, Boost guideline, `releasekit` skill with references, `docs/client-integration.md` | A new project can integrate using only the README. An agent working through Boost can build an admin release screen and register routes correctly without reading the package source |

---

## 19. Out of scope

- An admin UI of any kind.
- An apps table or multi-app support.
- Multi-tenancy.
- Translated release notes.
- Staged rollout percentages.
- Raw per-event analytics logs.
- `uploaded_by` or `published_by` columns. Hosts can add these with their own migration, or log them through events.
