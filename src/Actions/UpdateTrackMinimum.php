<?php

namespace TecClub\ReleaseKit\Actions;

use TecClub\ReleaseKit\Actions\Concerns\LocksTracks;
use TecClub\ReleaseKit\Actions\Concerns\RunsInTransaction;
use TecClub\ReleaseKit\Actions\Concerns\ValidatesInput;
use TecClub\ReleaseKit\Events\TrackMinimumUpdated;
use TecClub\ReleaseKit\Exceptions\InvalidVersion;
use TecClub\ReleaseKit\Models\Track;
use TecClub\ReleaseKit\Support\VersionComparator;

/**
 * Sets (or clears, with null) the minimum supported version of a track.
 * Clients below it get "update_required". It cannot exceed the current
 * release's version. The track is created if it does not exist yet.
 */
class UpdateTrackMinimum
{
    use LocksTracks;
    use RunsInTransaction;
    use ValidatesInput;

    public function handle(string $platform, string $channel, ?string $version): Track
    {
        $this->assertPlatform($platform);
        $this->assertChannel($channel);

        if ($version !== null) {
            VersionComparator::assertValid($version);
        }

        return $this->transaction(function () use ($platform, $channel, $version) {
            $track = $this->lockTrack($platform, $channel);
            $current = $track->currentRelease;

            if ($version !== null && $current !== null && VersionComparator::greaterThan($version, $current->version)) {
                throw InvalidVersion::minimumAboveCurrent($version, $current->version);
            }

            $previous = $track->minimum_supported_version;

            if ($previous === $version) {
                return $track;
            }

            $track->minimum_supported_version = $version;
            $track->save();

            TrackMinimumUpdated::dispatch($track, $previous);

            return $track;
        });
    }
}
