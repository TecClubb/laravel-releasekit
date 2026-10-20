<?php

namespace TecClub\ReleaseKit\Actions;

use TecClub\ReleaseKit\Actions\Concerns\LocksTracks;
use TecClub\ReleaseKit\Actions\Concerns\RunsInTransaction;
use TecClub\ReleaseKit\Events\ReleasePromoted;
use TecClub\ReleaseKit\Exceptions\InvalidPromotion;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Models\Track;
use TecClub\ReleaseKit\Support\ModelResolver;
use TecClub\ReleaseKit\Support\VersionComparator;

/**
 * Makes a published release the current release of its track. Promoting
 * an older release is how a rollback is done.
 */
class PromoteRelease
{
    use LocksTracks;
    use RunsInTransaction;

    /**
     * @param  Track|null  $track  Optional; must match the release's platform and channel.
     */
    public function handle(Release $release, ?Track $track = null): Track
    {
        return $this->transaction(function () use ($release, $track) {
            $release->refresh();

            if ($track !== null && ! $this->belongsTo($release, $track)) {
                throw InvalidPromotion::wrongTrack($release, $track);
            }

            return $this->promote($release, $this->lockTrack($release->platform, $release->channel));
        });
    }

    /**
     * Applies the promotion to a track that is already locked.
     *
     * @internal Used by YankRelease inside its own transaction.
     */
    public function promote(Release $release, Track $track): Track
    {
        if (! $this->belongsTo($release, $track)) {
            throw InvalidPromotion::wrongTrack($release, $track);
        }

        if (! $release->isPublished()) {
            throw InvalidPromotion::notPublished($release);
        }

        $minimum = $track->minimum_supported_version;

        if ($minimum !== null && VersionComparator::lessThan($release->version, $minimum)) {
            throw InvalidPromotion::belowMinimum($release, $minimum);
        }

        if ($track->current_release_id === $release->getKey()) {
            return $track;
        }

        $previous = $track->currentRelease;

        $track->currentRelease()->associate($release);
        $track->save();

        ReleasePromoted::dispatch($release, $track, $previous);

        return $track;
    }

    /**
     * Readiness warnings to show before confirming a promotion. Never throws
     * and never blocks; handle() enforces the hard rules.
     *
     * @return list<string>
     */
    public function warnings(Release $release): array
    {
        $warnings = [];

        $track = ModelResolver::track()::query()
            ->forPlatform($release->platform)
            ->forChannel($release->channel)
            ->with('currentRelease.artifacts')
            ->first();

        $current = $track?->currentRelease;
        $minimum = $track?->minimum_supported_version;

        if (! $release->isPublished()) {
            $warnings[] = "{$release->displayName()} is {$release->status->value}; publish it before making it current.";
        }

        if ($current?->is($release)) {
            $warnings[] = "{$release->displayName()} is already the current release.";

            return $warnings;
        }

        if ($minimum !== null && VersionComparator::lessThan($release->version, $minimum)) {
            $warnings[] = "{$release->displayName()} is below the minimum supported version {$minimum}.";
        }

        if ($current !== null && VersionComparator::lessThan($release->version, $current->version)) {
            $warnings[] = "{$release->displayName()} is older than the current release {$current->version}; this is a rollback.";
        }

        $artifacts = $release->artifacts()->active()->get();

        if ($artifacts->isEmpty() && ! $this->hasAnyLink($release)) {
            $warnings[] = "{$release->displayName()} has no active artifacts or links, so clients will have nothing to download.";
        }

        if ($current !== null) {
            foreach ($current->artifacts->where('is_active', true) as $existing) {
                if (! $this->covers($artifacts, $existing)) {
                    $architecture = $existing->architecture ?? 'universal';

                    $warnings[] = "{$release->displayName()} has no {$existing->format} build for {$architecture}, which the current release {$current->version} has.";
                }
            }
        }

        return array_values(array_unique($warnings));
    }

    private function belongsTo(Release $release, Track $track): bool
    {
        return $track->platform === $release->platform && $track->channel === $release->channel;
    }

    private function hasAnyLink(Release $release): bool
    {
        return $release->links()->active()->exists()
            || ModelResolver::link()::query()->forPlatform($release->platform)->permanent()->active()->exists();
    }

    /**
     * A universal build covers every architecture of the same format.
     *
     * @param  iterable<Artifact>  $artifacts
     */
    private function covers(iterable $artifacts, Artifact $existing): bool
    {
        foreach ($artifacts as $artifact) {
            if ($artifact->format === $existing->format
                && ($artifact->architecture === null || $artifact->architecture === $existing->architecture)) {
                return true;
            }
        }

        return false;
    }
}
