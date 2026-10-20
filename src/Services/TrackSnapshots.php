<?php

namespace TecClub\ReleaseKit\Services;

use TecClub\ReleaseKit\Data\ArtifactData;
use TecClub\ReleaseKit\Data\LinkData;
use TecClub\ReleaseKit\Data\ReleaseSummary;
use TecClub\ReleaseKit\Data\TrackSnapshot;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Link;
use TecClub\ReleaseKit\Support\ModelResolver;

/**
 * Loads the client-independent state of a platform + channel, through
 * ReleaseCache. Signed URLs are never part of it.
 */
class TrackSnapshots
{
    public function __construct(private readonly ReleaseCache $cache) {}

    public function get(string $platform, string $channel): TrackSnapshot
    {
        return TrackSnapshot::fromCache(
            $this->cache->remember($platform, $channel, fn () => $this->load($platform, $channel)->toCache())
        );
    }

    public function load(string $platform, string $channel): TrackSnapshot
    {
        $track = ModelResolver::track()::query()
            ->forPlatform($platform)
            ->forChannel($channel)
            ->with('currentRelease')
            ->first();

        // Defensive: a track pointed at a non-published release by a direct write offers nothing.
        $current = $track?->currentRelease;
        $current = $current?->isPublished() ? $current : null;

        $artifacts = $current?->artifacts()->active()->ordered()->get() ?? collect();

        $releaseLinks = $current?->links()->active()->ordered()->get() ?? collect();
        $platformLinks = ModelResolver::link()::query()->forPlatform($platform)->permanent()->active()->ordered()->get();

        return new TrackSnapshot(
            platform: $platform,
            channel: $channel,
            current: $current !== null ? ReleaseSummary::fromModel($current) : null,
            minimumSupportedVersion: $track?->minimum_supported_version,
            yankedVersions: ModelResolver::release()::query()
                ->forPlatform($platform)
                ->forChannel($channel)
                ->yanked()
                ->pluck('version')
                ->values()
                ->all(),
            artifacts: $artifacts->map(fn (Artifact $artifact) => ArtifactData::fromModel($artifact))->values()->all(),
            links: $releaseLinks->concat($platformLinks)
                ->map(fn (Link $link) => LinkData::fromModel($link))
                ->values()
                ->all(),
        );
    }
}
