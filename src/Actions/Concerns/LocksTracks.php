<?php

namespace TecClub\ReleaseKit\Actions\Concerns;

use TecClub\ReleaseKit\Models\Track;
use TecClub\ReleaseKit\Support\ModelResolver;

trait LocksTracks
{
    /**
     * Returns the track row locked for update. Call inside a transaction.
     *
     * @return ($create is true ? Track : Track|null)
     */
    protected function lockTrack(string $platform, string $channel, bool $create = true): ?Track
    {
        $query = fn () => ModelResolver::track()::query()
            ->where('platform', $platform)
            ->where('channel', $channel);

        if ($create) {
            // createOrFirst uses a savepoint, so a concurrent insert does not abort the transaction.
            $query()->createOrFirst(['platform' => $platform, 'channel' => $channel]);
        }

        return $query()->lockForUpdate()->first();
    }
}
