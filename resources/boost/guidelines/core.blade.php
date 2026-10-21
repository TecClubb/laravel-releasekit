## ReleaseKit (tecclub/releasekit)

ReleaseKit manages app releases, build artifacts, store links, tracks and version checks. It is headless: the host app builds the admin UI.

- **Write only through Actions** (`TecClub\ReleaseKit\Actions\*`, resolved with `app(...)->handle(...)`). Never create, update or delete `Release`, `Track`, `Artifact` or `Link` models directly. Reading through model scopes (`forPlatform`, `forChannel`, `published`, `drafts`, `yanked`, `active`) is fine.
- **Never compare versions manually.** Use `ReleaseKit::check()` or `TecClub\ReleaseKit\Support\VersionComparator`.
- Versions must match `^\d+(\.\d+){1,3}$` (e.g. `1.2`, `1.2.0`, `1.2.0.4`). No `-beta` or `+45` suffixes; use channels for beta builds.
- Platforms, architectures, formats, stores and channels are **config** (`config/releasekit.php`). Adding one is a config edit, never a migration or an enum.
- Publishing does not make a release live: `PublishRelease` then `PromoteRelease`. Show `app(PromoteRelease::class)->warnings($release)` before confirming a promotion.
- Published releases are never deleted. Use `YankRelease`, passing `rollbackTo:` when the release is current. Only drafts can be deleted (`DeleteDraftRelease`).
- Large build files: upload directly to storage, then pass the disk path to `AttachArtifact` instead of an `UploadedFile`.
- Routes are **not** auto-registered. Check `releasekit.routes.register` or the published `routes/releasekit.php`; keep the route names, signed download URLs depend on them.
- Catch `TecClub\ReleaseKit\Exceptions\ReleaseKitException` and show its message to the admin.
- For any task involving ReleaseKit, use the `releasekit` skill.
