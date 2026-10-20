# ReleaseKit — Build Progress

Living tracker for building `tecclub/releasekit`. The full design lives in [plan.md](plan.md); section numbers below (§) refer to it.
Update this file in the same PR as the work it describes.

**Workflow:** one branch + one PR per phase (`phase/N-name` → `main`). A phase is done when its "Done when" criteria pass locally and in CI.

| # | Phase | Branch | Status |
|---|---|---|---|
| 1 | Skeleton & foundation | `phase/1-skeleton` | 🟡 In review |
| 2 | Domain (Actions, exceptions, events, observers) | `phase/2-domain` | ⚪ Not started |
| 3 | Services & facade | `phase/3-services` | ⚪ Not started |
| 4 | HTTP API & routes | `phase/4-http` | ⚪ Not started |
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

- [ ] `ReleaseKitException` hierarchy
- [ ] Actions: `CreateRelease`, `UpdateRelease`, `PublishRelease`, `PromoteRelease` (+ `warnings()`), `YankRelease`, `UnyankRelease`, `DeleteDraftRelease`, `UpdateTrackMinimum`, `AttachArtifact` (record only; file handling in phase 5), `UpdateArtifact`, `DeleteArtifact`, `AddLink`, `UpdateLink`, `DeleteLink`
- [ ] Track rules in a transaction with row lock (§6.2)
- [ ] Events: `ReleaseCreated`, `ReleasePublished`, `ReleasePromoted`, `ReleaseYanked`, `TrackMinimumUpdated`, `ArtifactAttached` (`ArtifactDownloaded` lands in phase 4)
- [ ] `CacheInvalidationObserver` on all four core models
- [ ] Tests for every rule in §6 and §12

## Phase 3 — Services & facade

**Done when:** service tests pass without HTTP.

- [ ] `ReleaseCache` (per platform+channel payload, observer-driven invalidation, §10)
- [ ] `VersionChecker` (§7.3 algorithm)
- [ ] `DownloadResolver` (architecture selection, downloadable formats, link merging, §7.4)
- [ ] `DownloadUrlGenerator`, `ArtifactDelivery` (stream / signed / public + fallbacks, §9)
- [ ] `ChannelAccessGuard` (§8)
- [ ] `ReleaseKit` + facade: `check()`, `downloads()`, `releases()` (§7.2)

## Phase 4 — HTTP API & routes

**Done when:** API tests pass in both auto-registered and published-route modes.

- [ ] Controllers: `Check`, `Downloads`, `Releases`, `ArtifactDownload`
- [ ] Form requests + API resources (§7.3–7.6 shapes), error codes (§7.7)
- [ ] `routes/releasekit.php` stub, `releasekit-routes` + `releasekit-controllers` publish tags
- [ ] Opt-in auto-registration honouring `routes.endpoints`
- [ ] Signed download URLs for protected channels, `ArtifactDownloaded` event

## Phase 5 — Storage

**Done when:** a 1 GB+ file attaches without memory issues, and deletes clean up files.

- [ ] `AttachArtifact` file handling: `UploadedFile` (streamed) or existing disk path (moved), size limit
- [ ] `ComputeArtifactChecksum` job, `DeleteArtifactFile` job (after commit)
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
| 2026-10-20 | 1 | Migration files carry timestamp prefixes so they run in FK order (releases before tracks/artifacts/links). They are auto-loaded; hosts can opt out with `ReleaseKit::ignoreMigrations()` and publish them via `releasekit-migrations` instead. |
