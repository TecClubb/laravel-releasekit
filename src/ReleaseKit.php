<?php

namespace TecClub\ReleaseKit;

use TecClub\ReleaseKit\Support\ChannelRegistry;
use TecClub\ReleaseKit\Support\PlatformRegistry;

/**
 * Entry point behind the ReleaseKit facade.
 */
class ReleaseKit
{
    /**
     * Whether the package loads its own migrations.
     */
    public static bool $runsMigrations = true;

    public function __construct(
        private readonly PlatformRegistry $platforms,
        private readonly ChannelRegistry $channels,
    ) {}

    /**
     * Stop the package from loading its migrations, e.g. after publishing
     * them with --tag=releasekit-migrations. Call from a service provider's
     * register() method.
     */
    public static function ignoreMigrations(): void
    {
        static::$runsMigrations = false;
    }

    public function platforms(): PlatformRegistry
    {
        return $this->platforms;
    }

    public function channels(): ChannelRegistry
    {
        return $this->channels;
    }
}
