<?php

namespace TecClub\ReleaseKit\Facades;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Facade;
use TecClub\ReleaseKit\Data\CheckResult;
use TecClub\ReleaseKit\Data\DownloadsResult;
use TecClub\ReleaseKit\Data\PlatformDownloads;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Support\ChannelRegistry;
use TecClub\ReleaseKit\Support\PlatformRegistry;

/**
 * @method static CheckResult check(string $platform, string $version, ?string $channel = null, ?string $architecture = null)
 * @method static DownloadsResult downloads(?string $platform = null, ?string $channel = null, ?string $architecture = null)
 * @method static PlatformDownloads downloadsFor(string $platform, ?string $channel = null, ?string $architecture = null)
 * @method static Builder<Release> releases(?string $platform = null, ?string $channel = null)
 * @method static PlatformRegistry platforms()
 * @method static ChannelRegistry channels()
 *
 * @see \TecClub\ReleaseKit\ReleaseKit
 */
class ReleaseKit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \TecClub\ReleaseKit\ReleaseKit::class;
    }
}
