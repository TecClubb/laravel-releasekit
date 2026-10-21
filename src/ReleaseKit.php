<?php

namespace TecClub\ReleaseKit;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use TecClub\ReleaseKit\Data\CheckResult;
use TecClub\ReleaseKit\Data\DownloadsResult;
use TecClub\ReleaseKit\Data\PlatformDownloads;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Services\DownloadResolver;
use TecClub\ReleaseKit\Services\VersionChecker;
use TecClub\ReleaseKit\Support\ChannelRegistry;
use TecClub\ReleaseKit\Support\Concerns\ValidatesInput;
use TecClub\ReleaseKit\Support\ModelResolver;
use TecClub\ReleaseKit\Support\PlatformRegistry;

/**
 * Entry point behind the ReleaseKit facade. The HTTP controllers are thin
 * wrappers around these same calls.
 *
 * These methods do not check channel access: a host calling them directly
 * decides who sees protected channels. Artifact URLs for protected
 * channels are still temporary signed URLs.
 */
class ReleaseKit
{
    use ValidatesInput;

    /**
     * Whether the package loads its own migrations.
     */
    public static bool $runsMigrations = true;

    public function __construct(private readonly Container $container) {}

    /**
     * Stop the package from loading its migrations, e.g. after publishing
     * them with --tag=releasekit-migrations. Call from a service provider's
     * register() method.
     */
    public static function ignoreMigrations(): void
    {
        static::$runsMigrations = false;
    }

    public function check(string $platform, string $version, ?string $channel = null, ?string $architecture = null): CheckResult
    {
        return $this->container->make(VersionChecker::class)->check($platform, $version, $channel, $architecture);
    }

    /**
     * Current downloads for every platform, or one platform when given.
     */
    public function downloads(?string $platform = null, ?string $channel = null, ?string $architecture = null): DownloadsResult
    {
        return $this->container->make(DownloadResolver::class)->all($channel, $platform, $architecture);
    }

    public function downloadsFor(string $platform, ?string $channel = null, ?string $architecture = null): PlatformDownloads
    {
        return $this->container->make(DownloadResolver::class)->forPlatform($platform, $channel, $architecture);
    }

    /**
     * Published releases, newest first. Chain ->paginate() or ->get().
     *
     * @return Builder<Release>
     */
    public function releases(?string $platform = null, ?string $channel = null): Builder
    {
        $channel ??= $this->channels()->default();
        $this->assertChannel($channel);

        if ($platform !== null) {
            $this->assertPlatform($platform);
        }

        return ModelResolver::release()::query()
            ->published()
            ->forChannel($channel)
            ->when($platform !== null, fn (Builder $query) => $query->forPlatform($platform))
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    public function platforms(): PlatformRegistry
    {
        return $this->container->make(PlatformRegistry::class);
    }

    public function channels(): ChannelRegistry
    {
        return $this->container->make(ChannelRegistry::class);
    }
}
