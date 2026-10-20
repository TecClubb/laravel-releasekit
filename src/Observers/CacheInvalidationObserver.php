<?php

namespace TecClub\ReleaseKit\Observers;

use Illuminate\Database\Eloquent\Model;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Link;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Models\Track;
use TecClub\ReleaseKit\Services\ReleaseCache;
use TecClub\ReleaseKit\Support\ModelResolver;

/**
 * Clears the affected (platform, channel) cache entries on every save and
 * delete, so the cache stays correct even when hosts write to the models
 * directly instead of through the Actions.
 *
 * Both the new and the original values are cleared, so moving a row from
 * one platform or channel to another refreshes both.
 */
class CacheInvalidationObserver
{
    public function __construct(private readonly ReleaseCache $cache) {}

    public function saved(Model $model): void
    {
        $this->clear($model);
    }

    public function deleted(Model $model): void
    {
        $this->clear($model);
    }

    private function clear(Model $model): void
    {
        match (true) {
            $model instanceof Release, $model instanceof Track => $this->clearPlatformChannel($model),
            $model instanceof Artifact => $this->clearReleases($model),
            $model instanceof Link => $this->clearLink($model),
            default => null,
        };
    }

    private function clearPlatformChannel(Release|Track $model): void
    {
        $this->cache->forget($model->platform, $model->channel);

        $platform = $model->getOriginal('platform');
        $channel = $model->getOriginal('channel');

        if (is_string($platform) && is_string($channel)) {
            $this->cache->forget($platform, $channel);
        }
    }

    private function clearReleases(Artifact $artifact): void
    {
        $ids = array_unique(array_filter([
            $artifact->app_release_id,
            $artifact->getOriginal('app_release_id'),
        ]));

        // A missing release was deleted; its own observer call clears the cache.
        ModelResolver::release()::query()
            ->whereKey($ids)
            ->get(['id', 'platform', 'channel'])
            ->each(fn (Release $release) => $this->cache->forget($release->platform, $release->channel));
    }

    private function clearLink(Link $link): void
    {
        // Permanent links apply to every channel of the platform, and link
        // writes are rare, so clear the whole platform.
        $platforms = array_unique(array_filter([$link->platform, $link->getOriginal('platform')], 'is_string'));

        foreach ($platforms as $platform) {
            $this->cache->forgetPlatform($platform);
        }
    }
}
