# ReleaseKit HTTP API

`{prefix}` is `releasekit.routes.prefix` (default `api/releasekit`) when auto-registered, or wherever the host includes `routes/releasekit.php`. All endpoints are `GET`.

| Endpoint | Route name |
|---|---|
| `{prefix}/check` | `{name}check` |
| `{prefix}/downloads` | `{name}downloads` |
| `{prefix}/releases` | `{name}releases` |
| `{prefix}/artifacts/{artifact}/download` | `{name}artifacts.download` |

`{name}` is `releasekit.routes.name` (default `releasekit.`).

The same data is available without HTTP through the facade: `ReleaseKit::check()`, `ReleaseKit::downloads()`, `ReleaseKit::downloadsFor()`, `ReleaseKit::releases()`. The facade does **not** check channel access.

## Errors

Validation and access errors are always JSON, whatever the `Accept` header.

| Status | When |
|---|---|
| `422` | Unknown platform or channel, invalid version format, architecture not valid for the platform, bad paging. Laravel's `{ "message", "errors": { field: [...] } }` shape. |
| `403` | A protected channel requested without access: `{ "message": "Access to the beta channel is not allowed." }`. Also an invalid or expired signature on a protected download. |
| `404` | Artifact missing, inactive, not a downloadable format, or its release isn't published. |

## GET check

```
GET {prefix}/check?platform=android&version=1.1.0&channel=stable&architecture=arm64-v8a
```

| Param | Required | Notes |
|---|---|---|
| `platform` | yes | A configured platform |
| `version` | yes | `^\d+(\.\d+){1,3}$` — the version name only, no `+build` |
| `channel` | no | Defaults to `default_channel` |
| `architecture` | no | Must be one of the platform's architectures; filters artifacts |

Status, first match wins:

1. The client's version equals a yanked release → `blocked`
2. Below `minimum_supported_version` → `update_required`
3. Below the current release → `update_available`
4. Otherwise (incl. newer dev builds) → `up_to_date`

With no track or current release, rule 2 still applies; otherwise `up_to_date` with `current: null`.

```json
{
  "status": "update_available",
  "client_version": "1.1.0",
  "minimum_supported_version": "1.0.0",
  "current": { "version": "1.2.0", "build_number": "45", "release_notes": "…", "published_at": "2026-10-20T10:00:00Z" },
  "downloads": {
    "artifacts": [
      { "id": 12, "architecture": "arm64-v8a", "format": "apk", "label": "APK (arm64)", "size_bytes": 48211234, "checksum_sha256": "…", "url": "…" }
    ],
    "links": [
      { "type": "store", "store": "play_store", "label": "Google Play", "url": "https://play.google.com/…" }
    ]
  }
}
```

Not wrapped in `data`. With `architecture`, artifacts are exact matches plus universal (`architecture: null`) builds, ordered by `sort_order`. Links are the current release's links, then the platform's permanent links.

## GET downloads

```
GET {prefix}/downloads                    # all platforms
GET {prefix}/downloads?platform=windows
GET {prefix}/downloads?channel=beta       # protected: needs access
GET {prefix}/downloads?architecture=x64
```

```json
{
  "channel": "stable",
  "platforms": {
    "windows": {
      "label": "Windows",
      "current": { "version": "1.1.3", "build_number": "42", "published_at": "…" },
      "artifacts": [ { "id": 31, "architecture": "x64", "format": "exe", "label": null, "size_bytes": 0, "checksum_sha256": "…", "url": "…" } ],
      "links": [ { "type": "store", "store": "microsoft_store", "label": "Microsoft Store", "url": "…" } ]
    }
  }
}
```

Platforms with nothing to offer are omitted; an empty result is `"platforms": {}`. `current` can be `null` when a platform only has permanent links.

## GET releases

```
GET {prefix}/releases?platform=android&channel=stable&page=1&per_page=20
```

Published releases, newest `published_at` first. `per_page` defaults to 20, max 100. Standard Laravel pagination:

```json
{
  "data": [ { "version": "1.2.0", "build_number": "45", "release_notes": "…", "published_at": "2026-10-20T10:00:00Z" } ],
  "links": { "first": "…", "last": "…", "prev": null, "next": "…" },
  "meta": { "current_page": 1, "per_page": 20, "total": 7, "…": "…" }
}
```

Never exposes status, metadata or yank reasons.

## GET artifacts/{artifact}/download

Delivers the file according to `delivery.mode`: streamed (`200`), or a `302` to a temporary or public storage URL. `Content-Disposition` uses the original filename.

- Protected channels: the URL must be a valid, unexpired temporary signed URL (as returned by `check`/`downloads`); the signed link works without a session, e.g. in a download manager.
- Fires `ArtifactDownloaded`; records a `download` stat when analytics is on. It counts an initiated download, not a completed one.

## Artifact URLs

| Channel | `delivery.mode` | URL returned |
|---|---|---|
| protected | any | Temporary signed route URL (expires after `signed_url_ttl`) |
| public | `stream` / `signed` | App download route |
| public | `public` + `public_through_app: true` | App download route (counts, then redirects to storage) |
| public | `public` + `public_through_app: false` | Storage/CDN URL directly |

Don't cache API responses that contain protected-channel URLs for longer than `signed_url_ttl`.
