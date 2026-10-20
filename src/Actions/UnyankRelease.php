<?php

namespace TecClub\ReleaseKit\Actions;

use TecClub\ReleaseKit\Actions\Concerns\RunsInTransaction;
use TecClub\ReleaseKit\Enums\ReleaseStatus;
use TecClub\ReleaseKit\Events\ReleaseUnyanked;
use TecClub\ReleaseKit\Exceptions\InvalidStatusTransition;
use TecClub\ReleaseKit\Models\Release;

/**
 * Publishes a yanked release again. It does not become current; promote
 * it if needed. The original published_at is kept.
 */
class UnyankRelease
{
    use RunsInTransaction;

    public function handle(Release $release): Release
    {
        return $this->transaction(function () use ($release) {
            $release->refresh();

            if ($release->status !== ReleaseStatus::Yanked) {
                throw InvalidStatusTransition::to($release, ReleaseStatus::Published);
            }

            $release->status = ReleaseStatus::Published;
            $release->yanked_at = null;
            $release->yank_reason = null;
            $release->save();

            ReleaseUnyanked::dispatch($release);

            return $release;
        });
    }
}
