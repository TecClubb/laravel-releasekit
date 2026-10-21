# ReleaseKit client integration

The contract between an app (Flutter, native Android/iOS, desktop) and a Laravel backend running [ReleaseKit](https://github.com/TecClubb/laravel-releasekit). Hand this file to the app team, or drop it into the client repo as an agent skill.

The backend URL prefix is chosen by the backend team; this document uses `https://api.example.com/api/releasekit`.

## When to call `check`

- On app launch, and when the app returns to the foreground.
- At most once every few hours (e.g. 4 h). Store the time of the last successful check.
- Never block startup on it: run it in the background and react when it answers.

## What to send

```
GET /api/releasekit/check?platform=android&version=1.2.0&channel=stable&architecture=arm64-v8a
```

| Param | Value |
|---|---|
| `platform` | `android`, `ios`, `macos`, `windows`, `linux` (or another key the backend configured) |
| `version` | The **version name only**, e.g. `1.2.0` from `pubspec.yaml`, **without** `+45`. 2 to 4 numeric parts. |
| `channel` | Optional, defaults to the backend's default (usually `stable`). Beta builds send `beta`. |
| `architecture` | Optional. Send it where it applies so you get the right build: Android ABI (`arm64-v8a`, `armeabi-v7a`, `x86_64`), desktop CPU (`x64`, `arm64`, `x86`). Must be a value the backend configured for the platform. |

Protected channels (e.g. `beta`) need the same auth the app uses for its API (e.g. a Sanctum bearer token). Without access the answer is `403`.

> The backend relies on one rule: **every build that reaches users has a new version name.** A hotfix of `1.2.0+45` ships as `1.2.1`, never as `1.2.0+46`.

## The response

```json
{
  "status": "update_available",
  "client_version": "1.1.0",
  "minimum_supported_version": "1.0.0",
  "current": { "version": "1.2.0", "build_number": "45", "release_notes": "…", "published_at": "2026-10-20T10:00:00Z" },
  "downloads": {
    "artifacts": [
      { "id": 12, "architecture": "arm64-v8a", "format": "apk", "label": "APK (arm64)", "size_bytes": 48211234, "checksum_sha256": "…", "url": "https://…" }
    ],
    "links": [
      { "type": "store", "store": "play_store", "label": "Google Play", "url": "https://play.google.com/…" }
    ]
  }
}
```

`current` may be `null`. `release_notes` is Markdown. `checksum_sha256` may be `null` for a few seconds after a build is uploaded.

## How to handle each status

| `status` | Meaning | App behaviour |
|---|---|---|
| `blocked` | This exact version was pulled (e.g. a crash) | Full-screen, non-dismissible update screen |
| `update_required` | Below the minimum supported version | Full-screen, non-dismissible update screen |
| `update_available` | A newer release exists | Dismissible prompt, shown at most once per `current.version` |
| `up_to_date` | Nothing to do (also for dev builds newer than current) | Nothing |

Treat any status value you don't recognise as `up_to_date`.

## Fail open

If the request fails, times out (use ~10 s), or returns anything other than `200` with valid JSON, the app keeps working normally. A server outage must never lock users out. Don't show an error to the user.

## Where to send the user

1. **Store builds** (Play Store, App Store, Microsoft Store, Snap, Flathub): open the first `links` entry with `type: "store"`.
2. **Sideloaded / desktop builds**: use the first entry in `downloads.artifacts` (it is already filtered by the `architecture` you sent; universal builds have `architecture: null`). Fall back to the first link if there is no artifact.
3. If the app downloads the update itself, verify the file's SHA-256 against `checksum_sha256` (when present) before installing.

Artifact URLs on protected channels are signed and expire after a few minutes. Use them right away; never store them. Call `check` again to get a fresh URL.

## Flutter example

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:package_info_plus/package_info_plus.dart';

enum UpdateStatus { blocked, updateRequired, updateAvailable, upToDate }

class UpdateCheck {
  UpdateCheck(this.status, this.data);
  final UpdateStatus status;
  final Map<String, dynamic>? data;

  bool get mustUpdate => status == UpdateStatus.blocked || status == UpdateStatus.updateRequired;
}

Future<UpdateCheck> checkForUpdate({
  required String platform,     // 'android', 'ios', 'windows', ...
  String channel = 'stable',
  String? architecture,         // e.g. 'arm64-v8a'
  String? bearerToken,          // only for protected channels
}) async {
  try {
    final info = await PackageInfo.fromPlatform();
    final uri = Uri.https('api.example.com', '/api/releasekit/check', {
      'platform': platform,
      'version': info.version, // version name only, no build number
      'channel': channel,
      if (architecture != null) 'architecture': architecture,
    });

    final response = await http.get(uri, headers: {
      'Accept': 'application/json',
      if (bearerToken != null) 'Authorization': 'Bearer $bearerToken',
    }).timeout(const Duration(seconds: 10));

    if (response.statusCode != 200) return UpdateCheck(UpdateStatus.upToDate, null);

    final data = jsonDecode(response.body) as Map<String, dynamic>;
    final status = switch (data['status']) {
      'blocked' => UpdateStatus.blocked,
      'update_required' => UpdateStatus.updateRequired,
      'update_available' => UpdateStatus.updateAvailable,
      _ => UpdateStatus.upToDate,
    };

    return UpdateCheck(status, data);
  } catch (_) {
    return UpdateCheck(UpdateStatus.upToDate, null); // fail open
  }
}
```

## Other endpoints (websites, launchers)

- `GET /api/releasekit/downloads[?platform=…&channel=…&architecture=…]` — the current downloads for every platform, for a download page.
- `GET /api/releasekit/releases?platform=…&channel=…&page=1` — published releases, newest first, for a changelog.

## Errors

| Status | Meaning |
|---|---|
| `422` | Bad parameters (unknown platform/channel, invalid version format, unknown architecture). Fix the request; don't retry. |
| `403` | No access to a protected channel, or an expired download link. |
| `404` | The download is no longer available. Call `check` again. |
| `429` | Rate limited. Back off and try at the next launch. |
