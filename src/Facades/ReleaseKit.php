<?php

namespace TecClub\ReleaseKit\Facades;

use Illuminate\Support\Facades\Facade;
use TecClub\ReleaseKit\Support\ChannelRegistry;
use TecClub\ReleaseKit\Support\PlatformRegistry;

/**
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
