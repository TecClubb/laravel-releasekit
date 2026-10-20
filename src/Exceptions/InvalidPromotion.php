<?php

namespace TecClub\ReleaseKit\Exceptions;

use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Models\Track;

class InvalidPromotion extends ReleaseKitException
{
    public static function notPublished(Release $release): self
    {
        return new self("{$release->displayName()} is {$release->status->value}; only published releases can be made current.");
    }

    public static function wrongTrack(Release $release, Track $track): self
    {
        return new self("{$release->displayName()} cannot be made current on the {$track->platform} {$track->channel} track.");
    }

    public static function belowMinimum(Release $release, string $minimum): self
    {
        return new self("{$release->displayName()} is below the minimum supported version {$minimum}. Lower the minimum first.");
    }

    public static function rollbackToSelf(Release $release): self
    {
        return new self("{$release->displayName()} cannot be its own rollback target.");
    }
}
