<?php

namespace TecClub\ReleaseKit\Actions\Concerns;

use InvalidArgumentException;
use TecClub\ReleaseKit\Exceptions\DuplicateRelease;
use TecClub\ReleaseKit\Exceptions\ReleaseLocked;
use TecClub\ReleaseKit\Exceptions\UnsupportedValue;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Support\ChannelRegistry;
use TecClub\ReleaseKit\Support\ModelResolver;
use TecClub\ReleaseKit\Support\PlatformRegistry;
use TecClub\ReleaseKit\Support\VersionComparator;

/**
 * New writes are validated against config. Existing rows that reference
 * removed config keys keep loading; they just can't gain new values.
 */
trait ValidatesInput
{
    protected function assertPlatform(string $platform): void
    {
        if (! app(PlatformRegistry::class)->has($platform)) {
            throw UnsupportedValue::platform($platform);
        }
    }

    protected function assertChannel(string $channel): void
    {
        if (! app(ChannelRegistry::class)->has($channel)) {
            throw UnsupportedValue::channel($channel);
        }
    }

    protected function assertFormat(string $platform, string $format): void
    {
        if (! app(PlatformRegistry::class)->supportsFormat($platform, $format)) {
            throw UnsupportedValue::format($platform, $format);
        }
    }

    protected function assertArchitecture(string $platform, ?string $architecture): void
    {
        if ($architecture !== null && ! app(PlatformRegistry::class)->supportsArchitecture($platform, $architecture)) {
            throw UnsupportedValue::architecture($platform, $architecture);
        }
    }

    protected function assertStore(string $platform, ?string $store): void
    {
        if ($store !== null && ! app(PlatformRegistry::class)->supportsStore($platform, $store)) {
            throw UnsupportedValue::store($platform, $store);
        }
    }

    /**
     * Yanked releases only accept release-note changes.
     */
    protected function assertContentsEditable(?Release $release): void
    {
        if ($release?->isYanked()) {
            throw ReleaseLocked::contents($release);
        }
    }

    /**
     * Versions are compared, not string-matched, so "1.2" and "1.2.0"
     * can't both exist on the same platform and channel.
     */
    protected function assertVersionIsFree(string $platform, string $channel, string $version, ?Release $ignore = null): void
    {
        $taken = ModelResolver::release()::query()
            ->forPlatform($platform)
            ->forChannel($channel)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->pluck('version')
            ->contains(fn (string $existing) => VersionComparator::isValid($existing) && VersionComparator::equals($existing, $version));

        if ($taken) {
            throw DuplicateRelease::make($platform, $channel, $version);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $allowed
     */
    protected function assertKnownAttributes(array $attributes, array $allowed): void
    {
        $unknown = array_diff(array_keys($attributes), $allowed);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown attributes: '.implode(', ', $unknown).'. Allowed: '.implode(', ', $allowed).'.');
        }
    }
}
