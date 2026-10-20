<?php

namespace TecClub\ReleaseKit\Actions;

use TecClub\ReleaseKit\Actions\Concerns\LocksTracks;
use TecClub\ReleaseKit\Actions\Concerns\RunsInTransaction;
use TecClub\ReleaseKit\Enums\ReleaseStatus;
use TecClub\ReleaseKit\Events\ReleasePublished;
use TecClub\ReleaseKit\Exceptions\InvalidStatusTransition;
use TecClub\ReleaseKit\Models\Release;

/**
 * Moves a draft to published. Publishing does not make it current:
 * use PromoteRelease for that. The track for the platform and channel
 * is created on first publish.
 */
class PublishRelease
{
    use LocksTracks;
    use RunsInTransaction;

    public function handle(Release $release): Release
    {
        return $this->transaction(function () use ($release) {
            $release->refresh();

            if ($release->status !== ReleaseStatus::Draft) {
                throw InvalidStatusTransition::to($release, ReleaseStatus::Published);
            }

            $release->status = ReleaseStatus::Published;
            $release->published_at ??= now();
            $release->save();

            $this->lockTrack($release->platform, $release->channel);

            ReleasePublished::dispatch($release);

            return $release;
        });
    }
}
