# ReleaseKit — Build Progress

Living tracker for building `tecclub/releasekit`. The full design lives in [plan.md](plan.md); section numbers below (§) refer to it.
Update this file in the same PR as the work it describes.

**Workflow:** one branch + one PR per phase (`phase/N-name` → `main`). A phase is done when its "Done when" criteria pass locally and in CI.

| # | Phase | Branch | Status |
|---|---|---|---|
| 1 | Skeleton & foundation | `phase/1-skeleton` | ✅ Done ([#1](https://github.com/TecClubb/laravel-releasekit/pull/1)) |
| 2 | Domain (Actions, exceptions, events, observers) | `phase/2-domain` | ✅ Done ([#2](https://github.com/TecClubb/laravel-releasekit/pull/2)) |
| 3 | Services & facade | `phase/3-services` | ✅ Done ([#3](https://github.com/TecClubb/laravel-releasekit/pull/3)) |
| 4 | HTTP API & routes | `phase/4-http` | 🟡 In review |
| 5 | Storage (uploads, jobs, prune command) | `phase/5-storage` | ⚪ Not started |
| 6 | Analytics (optional stats) | `phase/6-analytics` | ⚪ Not started |
| 7 | Docs & Laravel Boost | `phase/7-docs-boost` | ⚪ Not started |

Legend: ⚪ not started · 🟡 in progress / in review · ✅ done (merged)

---

## Phase 1 — Skeleton & foundation

**Done when:** migrations run in Testbench; comparator and registry tests pass.

- [x] Package skeleton from `spatie/package-skeleton-laravel` (composer, Pest, PHPStan, Pint, CI workflows)
- [x] `ReleaseKitServiceProvider` on `spatie/laravel-package-tools`
- [x] `config/releasekit.php` (§4)
- [x] Core migrations: releases, tracks, artifacts, links (§5.1–5.4), table names from config
- [x] Analytics migration, publish-only under tag `releasekit-analytics-migrations` (§5.5)
- [x] Enums: `ReleaseStatus`, `LinkType`, `DeliveryMode`, `ChannelAccess`
- [x] Models: `Release`, `Track`, `Artifact`, `Link`, `Stat` (tables + related models resolved from config, read scopes)
- [x] Model factories for tests
- [x] `Support\VersionComparator` (§3 version format)
- [x] `Support\PlatformRegistry`, `Support\ChannelRegistry` (config-driven, unknown keys fall back to raw key)
- [x] `Support\DiskResolver` (channel disk → `storage.disk` → `filesystems.default`)
- [x] Tests: comparator, registries, disk resolution, migrations, models, arch rules
- [x] CI: SQLite matrix + MySQL + PostgreSQL jobs

## Phase 2 — Domain

**Done when:** all domain-rule tests in §6 pass.

- [x] `ReleaseKitException` hierarchy: `InvalidVersion`, `UnsupportedValue`, `DuplicateRelease`, `InvalidStatusTransition`, `ReleaseLocked`, `InvalidPromotion`, `ReleaseIsCurrent`, `InvalidLink`, `InvalidArtifactFile`
- [x] Actions: `CreateRelease`, `UpdateRelease`, `PublishRelease`, `PromoteRelease` (+ `warnings()`), `YankRelease`, `UnyankRelease`, `DeleteDraftRelease`, `UpdateTrackMinimum`, `AttachArtifact`, `UpdateArtifact`, `DeleteArtifact`, `AddLink`, `UpdateLink`, `DeleteLink`
- [x] Track rules in a transaction with row lock (§6.2)
- [x] Events (all dispatched after commit): `ReleaseCreated`, `ReleasePublished`, `ReleasePromoted`, `ReleaseYanked`, `ReleaseUnyanked`, `TrackMinimumUpdated`, `ArtifactAttached` (`ArtifactDownloaded` lands in phase 4)
- [x] `CacheInvalidationObserver` on all four core models + `Services\ReleaseCache` (keys, remember, forget)
- [x] Pulled forward from phase 5: `AttachArtifact` file handling (streamed upload or move of an existing path, size limit, rollback on DB failure) and the `DeleteArtifactFile` job
- [x] Tests for every rule in §6 and §12 (189 tests total)

## Phase 3 — Services & facade

**Done when:** service tests pass without HTTP.

- [x] `Services\TrackSnapshots` + `Data\TrackSnapshot`: the cached per platform+channel payload (current, minimum, yanked versions, artifacts, links) on `ReleaseCache` (§10). Cached as plain arrays; a warm check runs zero queries.
- [x] `VersionChecker` (§7.3 algorithm) → `Data\CheckResult` + `Enums\CheckStatus`
- [x] `DownloadResolver` (architecture selection, downloadable formats at read time, release + platform link merging, empty platforms omitted, §7.4) → `Data\DownloadsResult` / `PlatformDownloads`
- [x] `DownloadUrlGenerator` (signed route for protected, direct storage URL for public + `public_through_app=false`, else app route) and `ArtifactDelivery` (stream / signed / public + fallbacks, warn once, §9)
- [x] `ChannelAccessGuard` (§8): gate ability, configured guard, fail closed
- [x] `ReleaseKit` + facade: `check()`, `downloads()`, `downloadsFor()`, `releases()` (§7.2)
- [x] Tests: every §17 `VersionChecker` case, resolver, delivery modes + fallbacks, access, signed URLs not cached (248 tests total)

## Phase 4 — HTTP API & routes

**Done when:** API tests pass in both auto-registered and published-route modes.

- [x] Controllers: `Check`, `Downloads`, `Releases`, `ArtifactDownload` (invokable, thin)
- [x] Form requests (`CheckRequest`, `DownloadsRequest`, `ReleasesRequest`) + API resources (§7.3–7.6 shapes), error codes (§7.7): JSON 422 / 403 regardless of `Accept`, 404 for unavailable artifacts
- [x] `routes/releasekit.php` stub, `releasekit-routes` + `releasekit-controllers` publish tags (controller stubs are generated copies; a test fails if they drift)
- [x] Opt-in auto-registration (`Http\RouteRegistrar`) honouring `prefix`, `name`, `middleware`, `endpoints`; skipped when routes are cached
- [x] Signed download URLs verified for protected channels (403 invalid/expired), `ArtifactDownloaded` event
- [x] Tests in both modes: auto-registered and the published stub inside a host group, plus published controller copies (285 tests total)

## Phase 5 — Storage

**Done when:** a 1 GB+ file attaches without memory issues, and deletes clean up files.

- [x] `AttachArtifact` file handling: `UploadedFile` (streamed) or existing disk path (moved), size limit — done in phase 2
- [x] `DeleteArtifactFile` job (after commit) — done in phase 2
- [ ] `ComputeArtifactChecksum` job (fills `size_bytes`, `mime_type`, `checksum_sha256`), dispatched from `AttachArtifact`
- [ ] Verify a 1 GB+ file attaches without memory growth (both inputs)
- [ ] `releasekit:prune-orphans --dry-run` command

## Phase 6 — Analytics

**Done when:** disabled by default; counts are correct when enabled.

- [ ] `StatsRecorder` (atomic upserts, no-op when disabled or table missing)
- [ ] `Stat` scopes: `between`, `event`, `forPlatform`, `groupByVersion`
- [ ] Hooks in check + download paths, country header

## Phase 7 — Docs & Laravel Boost

**Done when:** a new project can integrate from the README alone; an agent via Boost can build an admin screen and register routes without reading the source.

- [ ] README
- [ ] `resources/boost/guidelines/core.blade.php`
- [ ] `resources/boost/skills/releasekit/SKILL.md` + references (`actions`, `config`, `api`, `client-integration`)
- [ ] `docs/client-integration.md`
- [ ] Drift test: every class named in Boost docs exists

---

## Decisions log

Choices made during the build that refine or deviate from the plan.

| Date | Phase | Decision |
|---|---|---|
| 2026-10-20 | 1 | Supports Laravel 12 & 13 (Testbench 10/11), PHP ^8.3. |
| 2026-10-20 | 1 | `VersionComparator` normalises to four parts and strips leading zeros before `version_compare`, so `1.2 == 1.2.0 == 01.2.0`. An arch test forbids `version_compare` anywhere else. |
| 2026-10-20 | 1 | Unknown channels or invalid `access` values are treated as **protected** (fail closed). |
| 2026-10-20 | 1 | Added `Support\ModelResolver` (model classes + table names from config) — used by every relationship and, later, every service. |
| 2026-10-20 | 1 | `Artifact` hides `disk`/`path` from serialisation by default; resources opt in for public delivery only. |
| 2026-10-20 | 1 | Release's "live on" relation is `currentOnTrack()` (HasOne via `current_release_id`). |
| 2026-10-20 | 1 | PHPStan raised from skeleton level 5 to level 6. |
| 2026-10-21 | 4 | Auto-registration is done by `Http\RouteRegistrar` (needed for the per-endpoint toggles); `routes/releasekit.php` is the publishable stub with plain `Route::` definitions under `config('releasekit.routes.name')`, so signed download URLs keep resolving. |
| 2026-10-21 | 4 | Validation and channel-access errors are always JSON (422 / 403), even without `Accept: application/json` — app clients and download managers rarely send it. |
| 2026-10-21 | 4 | `architecture` is validated against the platform's architectures in `check` and `downloads` (422 for e.g. `x64` on Android). |
| 2026-10-21 | 4 | `releases` endpoint takes `per_page` (default 20, max 100) and returns the standard paginated `{data, links, meta}` shape; `check` and `downloads` are unwrapped as in §7.3/§7.4. An empty `platforms` is `{}`, not `[]`. |
| 2026-10-21 | 4 | `{artifact}` is resolved manually through `ModelResolver` (not implicit binding) so host model subclasses work; non-numeric ids 404. |
| 2026-10-21 | 4 | `ArtifactDownloaded` carries the artifact and the effective `DeliveryMode`; it is not fired for direct-from-storage URLs (public mode without the app). |
| 2026-10-20 | 3 | Services and the facade do **not** enforce channel access; the HTTP layer does (via `ChannelAccessGuard`). A host calling the facade decides who sees protected channels. Protected artifact URLs are still always temporary signed route URLs. |
| 2026-10-20 | 3 | Snapshot stores every active artifact of the current release; `downloadable_formats` and architecture filtering happen at read time, so config edits apply without waiting for the cache TTL. |
| 2026-10-20 | 3 | Links order: the current release's links first, then permanent platform links, each by `sort_order`. Clients use the first store link. |
| 2026-10-20 | 3 | A track pointing at a non-published release (only possible via direct writes) is served as "no current release". |
| 2026-10-20 | 3 | Services validate platform/channel/version (`UnsupportedValue` / `InvalidVersion`) but not architecture — an unknown architecture just matches universal builds. Phase 4 requests can validate it strictly. |
| 2026-10-20 | 3 | A local disk counts as having public URLs only when its config has a `url`; otherwise public delivery falls back to signed. |
| 2026-10-20 | 3 | Missing download route throws `MissingDownloadRoute` explaining how to register it. |
| 2026-10-20 | 3 | `ValidatesInput` moved to `Support\Concerns` (shared by Actions and services). Added `ReleaseKit::downloadsFor()` returning one platform's `PlatformDownloads`. |
| 2026-10-20 | 2 | Status-changing Actions `refresh()` the release first, so a stale model can't e.g. publish a yanked release. |
| 2026-10-20 | 2 | Duplicate check compares versions (not strings): `1.2` and `1.2.0` can't both exist on one platform + channel. |
| 2026-10-20 | 2 | `UpdateRelease` takes an attribute array and ignores unchanged locked fields, so host forms can submit everything. Drafts: all fields; published: `release_notes`, `metadata`; yanked: `release_notes`. Platform can't change while a draft has artifacts/links; channel can't change while it has artifacts (files live on the channel's disk). |
| 2026-10-20 | 2 | Yanked releases lock their artifacts and links (add/update/delete) — "notes only" per §6.1. |
| 2026-10-20 | 2 | `PromoteRelease` rejects a release below the track minimum (applies to rollbacks too: lower the minimum first). `handle()` returns the `Track`; an optional `$track` argument is validated against the release. |
| 2026-10-20 | 2 | `YankRelease` ignores `rollbackTo` when the release isn't current; a rollback target equal to the release is rejected. |
| 2026-10-20 | 2 | `PromoteRelease::warnings()` returns `list<string>`: missing format/architecture vs current (a universal build covers all architectures), rollback, below minimum, not published, nothing to download, already current. |
| 2026-10-20 | 2 | Added a `ReleaseUnyanked` event (not in the plan's list) for audit logs. |
| 2026-10-20 | 2 | Link URLs: any valid URL incl. app-store schemes (`market://`, `itms-apps://`); `javascript:`, `data:`, `vbscript:`, `file:` are rejected. Link type is derived: store set → `store`, else `external`. |
| 2026-10-20 | 2 | Artifact storage keys use a slugged filename (`{ulid}-my-app-setup.exe`); the original name is kept in `original_filename` for `Content-Disposition`. |
| 2026-10-20 | 2 | Link writes clear the cache for every channel of the platform (permanent links apply to all channels). |
| 2026-10-20 | 2 | `.gitattributes` forces LF so Windows checkouts and Pint agree. |
| 2026-10-20 | 1 | Migration files carry timestamp prefixes so they run in FK order (releases before tracks/artifacts/links). They are auto-loaded; hosts can opt out with `ReleaseKit::ignoreMigrations()` and publish them via `releasekit-migrations` instead. |
