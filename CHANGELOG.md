# Changelog

All notable changes to `tecclub/releasekit` will be documented in this file.

## Unreleased

First release candidate.

- Releases per platform + channel + version with a draft → published → yanked lifecycle, artifacts (per architecture), store and external links, and tracks with an explicit current release and minimum supported version.
- Actions for every write, with admin-readable `ReleaseKitException`s and after-commit events.
- Version check (`up_to_date`, `update_available`, `update_required`, `blocked`), downloads and release history, as services, a facade, and an opt-in HTTP API with a publishable route stub.
- Stream, signed and public delivery with fallbacks; protected channels behind a Gate with temporary signed download URLs.
- Streamed uploads or direct-to-storage paths (1 GB+ tested), queued checksums, orphan pruning command.
- Observer-cleared cache: a warm version check runs zero queries.
- Optional daily aggregate analytics without personal data.
- Laravel Boost guideline and `releasekit` skill; client integration contract for app teams.
