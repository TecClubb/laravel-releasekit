<?php

namespace TecClub\ReleaseKit\Support;

use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Link;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Models\Stat;
use TecClub\ReleaseKit\Models\Track;

/**
 * Resolves model classes from config('releasekit.models') so hosts can
 * swap in their own subclasses.
 */
final class ModelResolver
{
    /** @return class-string<Release> */
    public static function release(): string
    {
        return config('releasekit.models.release', Release::class);
    }

    /** @return class-string<Track> */
    public static function track(): string
    {
        return config('releasekit.models.track', Track::class);
    }

    /** @return class-string<Artifact> */
    public static function artifact(): string
    {
        return config('releasekit.models.artifact', Artifact::class);
    }

    /** @return class-string<Link> */
    public static function link(): string
    {
        return config('releasekit.models.link', Link::class);
    }

    /** @return class-string<Stat> */
    public static function stat(): string
    {
        return config('releasekit.models.stat', Stat::class);
    }

    /**
     * @param  'releases'|'tracks'|'artifacts'|'links'|'stats'  $key
     */
    public static function table(string $key): string
    {
        $default = $key === 'releases' ? 'app_releases' : "app_release_{$key}";

        return (string) config("releasekit.tables.{$key}", $default);
    }
}
