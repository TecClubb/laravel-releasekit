---
name: releasekit
description: "Use when working with tecclub/releasekit: building admin screens for releases, artifacts, links, or tracks; adding platforms, architectures, or channels; registering or customizing the ReleaseKit API routes; setting up protected channels or delivery modes; building a website downloads or changelog page; enabling analytics; or integrating the version-check API in a client app."
license: MIT
metadata:
  author: tecclub
---
# ReleaseKit

ReleaseKit (`tecclub/releasekit`) stores releases per platform + channel + version, the build files (artifacts) and store links attached to them, and one **track** per platform + channel that decides what clients receive (the current release and a minimum supported version). It is headless: the host builds the UI on top of the Actions.

Detailed references (read the one the task needs):

- `references/actions.md` — every Action: signature, rules, exceptions, events
- `references/config.md` — every config key, default and effect
- `references/api.md` — HTTP endpoints, parameters, responses, errors
- `references/client-integration.md` — the contract for app teams (Flutter etc.)

## Core rules

- Write through `TecClub\ReleaseKit\Actions\*` only. Read through models and scopes.
- Every rule violation throws a `TecClub\ReleaseKit\Exceptions\ReleaseKitException` subclass whose message is safe to show to an admin.
- Never compare versions yourself; use `TecClub\ReleaseKit\Support\VersionComparator` (`1.2` equals `1.2.0`).
- Lifecycle: `draft` → `published` → `yanked` (and `yanked` → `published` to un-yank). Publishing is not going live; promoting is.

## 1. Build admin screens

Lists read models directly; writes go through Actions.

```php
use TecClub\ReleaseKit\Models\Release;

$releases = Release::whereIn('platform', ['android', 'windows'])->forChannel('stable')
    ->latest()->with(['artifacts', 'links', 'currentOnTrack'])->paginate(20);
```

Column names per model are listed in `references/actions.md` (Reading).

The primary flow is **draft → upload → publish → promote**:

```php
use TecClub\ReleaseKit\Actions\{CreateRelease, AttachArtifact, AddLink, PublishRelease, PromoteRelease};
use TecClub\ReleaseKit\Exceptions\ReleaseKitException;

try {
    $release = app(CreateRelease::class)->handle(platform: 'android', version: '1.2.0', buildNumber: '45', releaseNotes: $notes);
    app(AttachArtifact::class)->handle($release, $request->file('build'), format: 'apk', architecture: 'arm64-v8a');
    app(AddLink::class)->handle(platform: 'android', store: 'play_store', url: 'https://play.google.com/store/apps/details?id=…'); // permanent link
    app(PublishRelease::class)->handle($release);
} catch (ReleaseKitException $e) {
    return back()->withErrors(['release' => $e->getMessage()]);
}
```

Before promoting, show warnings and ask for confirmation; they never block:

```php
$warnings = app(PromoteRelease::class)->warnings($release); // list<string>
// … after the admin confirms:
app(PromoteRelease::class)->handle($release);
```

Other screens:

- Edit: `UpdateRelease::handle($release, [...])` accepts a full form payload; unchanged locked fields are ignored. Drafts: all fields. Published: `release_notes`, `metadata`. Yanked: `release_notes`.
- Yank: `YankRelease::handle($release, reason: '…', rollbackTo: $previous)` — `rollbackTo` is required when the release is current. `$release->currentOnTrack` is the `Track` it is live on, or `null` when it isn't current. `references/actions.md` shows how to suggest a rollback target.
- Minimum version (force update): `UpdateTrackMinimum::handle('android', 'stable', '1.1.0')`; `null` clears it.
- Delete: only drafts, via `DeleteDraftRelease`. Artifacts: `UpdateArtifact` / `DeleteArtifact`; links: `UpdateLink` / `DeleteLink`.

Large builds: let the browser upload straight to storage (Livewire S3 temporary uploads, or presigned PUT URLs), then pass the stored path: `AttachArtifact::handle($release, 'tmp/uploads/app.apk', format: 'apk')`. The file is moved to its final path (server-side copy on S3/R2). A queued job fills in `checksum_sha256`.

## 2. Add a platform, architecture, format, store or channel

Edit `config/releasekit.php` only — never a migration or enum.

```php
'platforms' => [
    'tvos' => [
        'label' => 'tvOS',
        'architectures' => [],
        'formats' => ['ipa'],
        'downloadable_formats' => [],   // formats the API may offer
        'stores' => ['app_store'],      // keys from 'stores'
    ],
],
'channels' => ['nightly' => ['access' => 'protected', 'disk' => 'private-builds']],
```

A format is only offered through the API when it is also in `downloadable_formats`. A store must exist in `stores` and be listed on the platform.

## 3. Register routes

Routes are off by default. Either set `releasekit.routes.register = true` (uses `routes.prefix`, `routes.name`, `routes.middleware`, `routes.endpoints`), or publish the stub and include it yourself:

```bash
php artisan vendor:publish --tag=releasekit-routes
```

```php
// Inside an existing versioned group:
Route::prefix('api/v1')->middleware('api')->group(function () {
    Route::prefix('releasekit')
        ->middleware('throttle:120,1')   // the stub adds no middleware or prefix of its own
        ->group(base_path('routes/releasekit.php'));
});
```

The stub wraps its routes in `Route::name(config('releasekit.routes.name'))` (default `releasekit.`), giving `releasekit.check`, `releasekit.downloads`, `releasekit.releases`, `releasekit.artifacts.download`. Download URLs are generated from `{routes.name}artifacts.download`, so **don't add a `->name()` prefix around the include**; to rename, change `releasekit.routes.name` instead. `routes.prefix`, `routes.middleware` and `routes.endpoints` only apply to auto-registration, so add throttling yourself when including the stub, and delete endpoint lines you don't want. If the host must rename it, bind its own `TecClub\ReleaseKit\Services\DownloadUrlGenerator`. Point routes at the package controllers; only publish controller copies (`--tag=releasekit-controllers`) when a different response shape is needed.

## 4. Set up a protected channel (e.g. beta)

```php
// config/releasekit.php
'channels' => ['beta' => ['access' => 'protected', 'disk' => 'r2-private']],
'access' => ['ability' => 'releasekit.access-channel', 'guard' => 'sanctum'],

// AppServiceProvider::boot()
Gate::define('releasekit.access-channel', fn (?User $user, string $channel) => $user?->isBetaTester() ?? false);
```

The `?User` must be nullable or guests are denied before the closure runs. Artifact URLs for protected channels are always temporary signed URLs. Some storage providers (Cloudflare R2) make a whole bucket public, so put protected channels on a separate private disk when public channels use `public` delivery.

## 5. Website downloads or changelog page

Same Laravel app — use the facade (no HTTP, no access check: you decide who sees protected channels):

```php
use TecClub\ReleaseKit\Facades\ReleaseKit;

$downloads = ReleaseKit::downloads(channel: 'stable');              // every platform with something to offer
$windows   = ReleaseKit::downloadsFor('windows', architecture: 'x64');
$history   = ReleaseKit::releases(platform: 'android')->paginate(20);
```

Separate frontend — call `GET {prefix}/downloads` and `GET {prefix}/releases` (see `references/api.md`). Do not cache the returned artifact URLs: on protected channels they expire after `delivery.signed_url_ttl`.

## 6. Enable analytics

```bash
php artisan vendor:publish --tag=releasekit-analytics-migrations && php artisan migrate
```

Set `releasekit.analytics.enabled = true`. Daily counts of checks (by client version) and downloads (by artifact) are stored; no IPs or device IDs. Query them with scopes:

```php
use TecClub\ReleaseKit\Enums\StatEvent;
use TecClub\ReleaseKit\Models\Stat;

Stat::event(StatEvent::Check)->forPlatform('android')->between(now()->subDays(30), now())->groupByVersion()->get(); // version, total
```

Downloads served straight from storage (`delivery.mode = public` with `public_through_app = false`) can't be counted.

## 7. Integrate the version check in a client app

See `references/client-integration.md`: when to call `check`, what to send, how to handle each status, fail open on errors.
