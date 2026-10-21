# ReleaseKit Actions

All Actions live in `TecClub\ReleaseKit\Actions` and are resolved from the container: `app(CreateRelease::class)->handle(...)`. Named arguments are recommended.

Every rule violation throws a subclass of `TecClub\ReleaseKit\Exceptions\ReleaseKitException` with an admin-readable message. Passing an attribute an `Update*` Action doesn't know throws `InvalidArgumentException` (a programming error, not a user error).

Events implement `ShouldDispatchAfterCommit`: they fire only after the surrounding transaction commits.

## Exceptions

All in `TecClub\ReleaseKit\Exceptions\`, all extending `ReleaseKitException`. Catching the base class is usually enough.

| Exception | Thrown when |
|---|---|
| `InvalidVersion` | A version doesn't match `^\d+(\.\d+){1,3}$`, or a minimum is above the current release |
| `UnsupportedValue` | A platform, channel, format, architecture or store isn't in config (or not allowed for the platform) |
| `DuplicateRelease` | An equal version already exists on the platform + channel (`1.2` equals `1.2.0`) |
| `InvalidStatusTransition` | The status change isn't allowed (only draft → published, published → yanked, yanked → published) |
| `ReleaseLocked` | A field the release's status locks was changed, a non-draft is deleted, a yanked release's artifacts/links are changed, or platform/channel changes while a draft has artifacts/links |
| `InvalidPromotion` | Promoting a non-published release, onto another platform/channel's track, below the track minimum, or rolling back to itself |
| `ReleaseIsCurrent` | Yanking the current release without `rollbackTo` |
| `InvalidLink` | Bad or unsafe URL (`javascript:`, `data:`, `vbscript:`, `file:`), missing platform, or platform differs from the release |
| `InvalidArtifactFile` | The file is missing on the disk, larger than `storage.max_upload_size_mb`, or unreadable |
| `MissingDownloadRoute` | Download URLs are generated but the `…artifacts.download` route isn't registered |

## Releases

### CreateRelease

```php
handle(string $platform, string $version, ?string $channel = null, ?string $buildNumber = null, ?string $releaseNotes = null, ?array $metadata = null): Release
```

Creates a **draft**. `$channel` defaults to `releasekit.default_channel`. `$buildNumber` is display-only.
Throws `UnsupportedValue`, `InvalidVersion`, `DuplicateRelease`. Fires `ReleaseCreated`.

### UpdateRelease

```php
handle(Release $release, array $attributes): Release
```

Keys: `platform`, `channel`, `version`, `build_number`, `release_notes`, `metadata`. Unchanged values are ignored, so a full form payload is fine.

| Status | Editable |
|---|---|
| draft | everything |
| published | `release_notes`, `metadata` |
| yanked | `release_notes` |

A draft's platform can't change while it has artifacts or links, and its channel can't change while it has artifacts (files live on the channel's disk). On failure the model is left unchanged.
Throws `ReleaseLocked`, `UnsupportedValue`, `InvalidVersion`, `DuplicateRelease`.

### PublishRelease

```php
handle(Release $release): Release
```

Draft → published. Sets `published_at` the first time. Creates the platform + channel track if it doesn't exist, **but does not make the release current**.
Throws `InvalidStatusTransition`. Fires `ReleasePublished`.

### PromoteRelease

```php
handle(Release $release, ?Track $track = null): Track
warnings(Release $release): list<string>
```

Makes a published release the track's current release. Promoting an older release is a rollback. Runs in a transaction with a row lock on the track. Promoting the release that is already current is a no-op (no event).
Throws `InvalidPromotion` (not published; `$track` of another platform/channel; version below the track's minimum — lower the minimum first). Fires `ReleasePromoted` (`release`, `track`, `previous`).

`warnings()` never throws and never blocks. Show it before confirming. It reports: not published yet, already current, below the minimum, older than current (rollback), nothing to download (no active artifacts and no links), and every format/architecture the current release has that this one lacks (a universal build covers all architectures of its format).

### YankRelease

```php
handle(Release $release, ?string $reason = null, ?Release $rollbackTo = null): Release
```

Published → yanked. Clients on this exact version get `blocked`. Its downloads stop. `$reason` is internal (max 255 chars, never exposed by the API).
If the release is **current**, `$rollbackTo` is required: the track moves to it (same rules as `PromoteRelease`) and the release is yanked in one transaction. When the release isn't current, `$rollbackTo` is ignored. The rollback target must be at or above the track's minimum supported version; if it isn't, lower the minimum with `UpdateTrackMinimum` first (or nothing changes and `InvalidPromotion` is thrown).

```php
$isCurrent = $release->currentOnTrack()->exists();   // currentOnTrack is a HasOne<Track>, null when not current

// Suggest the newest other published release as the rollback target:
$candidate = Release::forPlatform($release->platform)->forChannel($release->channel)
    ->published()->whereKeyNot($release->getKey())
    ->latest('published_at')->first();
```
Throws `InvalidStatusTransition`, `ReleaseIsCurrent`, `InvalidPromotion`. Fires `ReleaseYanked` (`release`, `rollbackTo`) and, when moved, `ReleasePromoted`.

### UnyankRelease

```php
handle(Release $release): Release
```

Yanked → published. Clears `yanked_at` and `yank_reason`, keeps `published_at`. Does not make it current.
Throws `InvalidStatusTransition`. Fires `ReleaseUnyanked`.

### DeleteDraftRelease

```php
handle(Release $release): void
```

Deletes a draft with its artifacts (files queued for deletion after commit) and release links. Published and yanked releases are never deleted.
Throws `ReleaseLocked`.

## Tracks

### UpdateTrackMinimum

```php
handle(string $platform, string $channel, ?string $version): Track
```

Sets the minimum supported version (clients below it get `update_required`); `null` clears it. Can't exceed the current release's version. Creates the track if needed. Row-locked transaction.
Throws `UnsupportedValue`, `InvalidVersion`. Fires `TrackMinimumUpdated` (`track`, `previousMinimum`) when the value changes.

## Artifacts

### AttachArtifact

```php
handle(Release $release, UploadedFile|string $file, string $format, ?string $architecture = null, ?string $label = null, int $sortOrder = 0, ?array $metadata = null): Artifact
```

Attaches a build file to a draft or published release. `$architecture = null` means universal. Lowest `sort_order` is primary.

- `UploadedFile`: streamed to the disk, never loaded into memory.
- `string`: a path that already exists on the release's disk (recommended for large builds uploaded directly to storage). It is moved to the final path.

You don't need to validate `format`/`architecture` in the host request: the Action checks them against config. Uploads through PHP are also limited by `upload_max_filesize` / `post_max_size` (and your web server), which are usually far below `storage.max_upload_size_mb`; for big builds upload straight to storage and pass the path.

Disk: the channel's `disk`, else `storage.disk`, else the default disk. Path: `{directory}/{channel}/{platform}/{version}/{ulid}-{slugged-filename}`; the original name is kept for `Content-Disposition`. If the DB write fails, the stored file is removed (or moved back). After commit, the queued `ComputeArtifactChecksum` job fills `checksum_sha256` (and missing size/MIME type).
Throws `ReleaseLocked` (yanked), `UnsupportedValue`, `InvalidArtifactFile`. Fires `ArtifactAttached`.

### UpdateArtifact

```php
handle(Artifact $artifact, array $attributes): Artifact
```

Keys: `architecture`, `label`, `is_active`, `sort_order`, `metadata`. The file, format and storage location are fixed: attach a new artifact to replace a build.
Throws `ReleaseLocked` (yanked), `UnsupportedValue`.

### DeleteArtifact

```php
handle(Artifact $artifact): void
```

Deletes the row; the `DeleteArtifactFile` job removes the file after commit (a failed file delete only logs a warning).
Throws `ReleaseLocked` (yanked).

## Links

### AddLink

```php
handle(string $url, ?string $platform = null, ?Release $release = null, ?string $store = null, ?string $label = null, int $sortOrder = 0, bool $isActive = true): Link
```

Without `$release`: a permanent platform link (shown for every release and channel of the platform). With `$release`: belongs to that release and takes its platform. Passing `$store` makes it a `store` link (label falls back to the store's label); otherwise `external`. App-store URL schemes like `market://` and `itms-apps://` are allowed. It never de-duplicates: check first if a seeder may run twice (`Link::forPlatform('android')->permanent()->where('store', 'play_store')->exists()`).
Throws `InvalidLink`, `UnsupportedValue`, `ReleaseLocked` (yanked release).

### UpdateLink

```php
handle(Link $link, array $attributes): Link
```

Keys: `url`, `store`, `label`, `is_active`, `sort_order`. Platform and release are fixed. Setting `store` to `null` makes it external.
Throws `InvalidLink`, `UnsupportedValue`, `ReleaseLocked`.

### DeleteLink

```php
handle(Link $link): void
```

Throws `ReleaseLocked` (yanked release).

## Events

`TecClub\ReleaseKit\Events\`: `ReleaseCreated`, `ReleasePublished`, `ReleasePromoted`, `ReleaseYanked`, `ReleaseUnyanked`, `TrackMinimumUpdated`, `ArtifactAttached`, `ArtifactDownloaded` (fired by the download endpoint with the artifact and the effective `DeliveryMode`).

## Reading

Models (`TecClub\ReleaseKit\Models\`) resolve from `releasekit.models.*`:

| Model | Scopes / helpers |
|---|---|
| `Release` | `forPlatform`, `forChannel`, `published`, `drafts`, `yanked`, `withStatus`; `isDraft()`, `isPublished()`, `isYanked()`, `displayName()`; relations `artifacts`, `links`, `currentOnTrack` |
| `Track` | `forPlatform`, `forChannel`; relation `currentRelease` |
| `Artifact` | `active`, `forArchitecture` (exact + universal), `ordered`; relation `release`; `path` and `disk` are hidden from serialisation |
| `Link` | `active`, `forPlatform`, `permanent`, `ordered`; `displayLabel()`; relation `release` |
| `Stat` | see analytics in `config.md` / the skill |

Columns (table names come from `releasekit.tables.*`; filter several values with plain `whereIn`, e.g. `Release::whereIn('platform', ['android', 'windows'])`):

| Model | Columns |
|---|---|
| `Release` | `id`, `platform`, `channel`, `version`, `build_number`, `status` (`ReleaseStatus` enum), `release_notes`, `metadata` (array), `published_at`, `yanked_at`, `yank_reason`, timestamps |
| `Track` | `id`, `platform`, `channel`, `current_release_id`, `minimum_supported_version`, timestamps |
| `Artifact` | `id`, `app_release_id`, `architecture` (null = universal), `format`, `label`, `disk`, `path`, `original_filename`, `mime_type`, `size_bytes`, `checksum_sha256`, `is_active`, `sort_order`, `metadata`, timestamps |
| `Link` | `id`, `platform`, `app_release_id` (null = permanent), `type` (`LinkType` enum: `store`/`external`), `store`, `label`, `url`, `is_active`, `sort_order`, timestamps |

Release lists: `latest()` orders by `created_at`; use `latest('published_at')` for published releases.

Cache invalidation lives in model observers, so direct model writes keep the cache correct, but they bypass every rule above. Don't write models directly.
