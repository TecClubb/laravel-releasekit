<?php

namespace TecClub\ReleaseKit\Services;

use Closure;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository;
use TecClub\ReleaseKit\Support\ChannelRegistry;

/**
 * Caches the client-independent payload per (platform, channel).
 *
 * Entries are cleared by CacheInvalidationObserver whenever a release,
 * track, artifact or link changes; the TTL is only a safety net.
 */
class ReleaseCache
{
    public function __construct(
        private readonly Repository $config,
        private readonly Factory $cache,
        private readonly ChannelRegistry $channels,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->config->get('releasekit.cache.enabled', true);
    }

    public function key(string $platform, string $channel): string
    {
        return "releasekit:{$platform}:{$channel}";
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public function remember(string $platform, string $channel, Closure $callback): mixed
    {
        if (! $this->enabled()) {
            return $callback();
        }

        return $this->store()->remember(
            $this->key($platform, $channel),
            (int) $this->config->get('releasekit.cache.ttl', 300),
            $callback,
        );
    }

    public function forget(string $platform, string $channel): void
    {
        $this->store()->forget($this->key($platform, $channel));
    }

    /**
     * Clears every configured channel of a platform, e.g. after a
     * permanent platform link changes.
     */
    public function forgetPlatform(string $platform): void
    {
        foreach ($this->channels->keys() as $channel) {
            $this->forget($platform, $channel);
        }
    }

    private function store(): Cache
    {
        return $this->cache->store($this->config->get('releasekit.cache.store'));
    }
}
