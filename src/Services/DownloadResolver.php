<?php

namespace TecClub\ReleaseKit\Services;

use TecClub\ReleaseKit\Data\ArtifactData;
use TecClub\ReleaseKit\Data\ArtifactDownload;
use TecClub\ReleaseKit\Data\DownloadsResult;
use TecClub\ReleaseKit\Data\PlatformDownloads;
use TecClub\ReleaseKit\Data\TrackSnapshot;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Support\ChannelRegistry;
use TecClub\ReleaseKit\Support\Concerns\ValidatesInput;
use TecClub\ReleaseKit\Support\PlatformRegistry;

/**
 * Resolves what a channel offers per platform: the track's current release,
 * its active downloadable artifacts, its active links and the platform's
 * active permanent links.
 *
 * With an architecture, only exact matches plus universal artifacts are
 * returned; without one, everything is. Artifacts keep sort_order order.
 *
 * Does not check channel access; the HTTP layer does that through
 * ChannelAccessGuard.
 */
class DownloadResolver
{
    use ValidatesInput;

    public function __construct(
        private readonly TrackSnapshots $snapshots,
        private readonly PlatformRegistry $platforms,
        private readonly ChannelRegistry $channels,
        private readonly DownloadUrlGenerator $urls,
    ) {}

    /**
     * Every configured platform (or just $platform) that has something to offer.
     */
    public function all(?string $channel = null, ?string $platform = null, ?string $architecture = null): DownloadsResult
    {
        $channel ??= $this->channels->default();
        $this->assertChannel($channel);

        if ($platform !== null) {
            $this->assertPlatform($platform);
        }

        $offered = [];

        foreach ($platform !== null ? [$platform] : $this->platforms->keys() as $key) {
            $downloads = $this->fromSnapshot($this->snapshots->get($key, $channel), $architecture);

            if (! $downloads->isEmpty()) {
                $offered[$key] = $downloads;
            }
        }

        return new DownloadsResult($channel, $offered);
    }

    /**
     * One platform's downloads; may be empty.
     */
    public function forPlatform(string $platform, ?string $channel = null, ?string $architecture = null): PlatformDownloads
    {
        $channel ??= $this->channels->default();
        $this->assertPlatform($platform);
        $this->assertChannel($channel);

        return $this->fromSnapshot($this->snapshots->get($platform, $channel), $architecture);
    }

    public function fromSnapshot(TrackSnapshot $snapshot, ?string $architecture = null): PlatformDownloads
    {
        $artifacts = array_values(array_filter(
            $snapshot->artifacts,
            fn (ArtifactData $artifact) => $this->platforms->isDownloadableFormat($snapshot->platform, $artifact->format)
                && ($architecture === null || $artifact->architecture === null || $artifact->architecture === $architecture),
        ));

        return new PlatformDownloads(
            platform: $snapshot->platform,
            label: $this->platforms->label($snapshot->platform),
            current: $snapshot->current,
            artifacts: array_map(
                fn (ArtifactData $artifact) => new ArtifactDownload($artifact, $this->urls->url($artifact, $snapshot->channel)),
                $artifacts,
            ),
            links: $snapshot->links,
        );
    }

    /**
     * Whether the download endpoint may serve this artifact: active, a
     * downloadable format, and its release published.
     */
    public function isDownloadable(Artifact $artifact): bool
    {
        $release = $artifact->release;

        return $artifact->is_active
            && $release->isPublished()
            && $this->platforms->isDownloadableFormat($release->platform, $artifact->format);
    }
}
