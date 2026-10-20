<?php

namespace TecClub\ReleaseKit\Actions;

use TecClub\ReleaseKit\Actions\Concerns\LocksTracks;
use TecClub\ReleaseKit\Actions\Concerns\RunsInTransaction;
use TecClub\ReleaseKit\Enums\ReleaseStatus;
use TecClub\ReleaseKit\Events\ReleaseYanked;
use TecClub\ReleaseKit\Exceptions\InvalidPromotion;
use TecClub\ReleaseKit\Exceptions\InvalidStatusTransition;
use TecClub\ReleaseKit\Exceptions\ReleaseIsCurrent;
use TecClub\ReleaseKit\Models\Release;

/**
 * Yanks a published release: it stops being offered and clients still on
 * it are told they are blocked.
 *
 * If the release is current, pass $rollbackTo; the track moves to it and
 * the release is yanked in one transaction. $rollbackTo is ignored when
 * the release is not current.
 */
class YankRelease
{
    use LocksTracks;
    use RunsInTransaction;

    public function __construct(private readonly PromoteRelease $promote) {}

    public function handle(Release $release, ?string $reason = null, ?Release $rollbackTo = null): Release
    {
        return $this->transaction(function () use ($release, $reason, $rollbackTo) {
            $release->refresh();

            if (! $release->status->canTransitionTo(ReleaseStatus::Yanked)) {
                throw InvalidStatusTransition::to($release, ReleaseStatus::Yanked);
            }

            $track = $this->lockTrack($release->platform, $release->channel, create: false);
            $movedTo = null;

            if ($track !== null && $track->current_release_id === $release->getKey()) {
                if ($rollbackTo === null) {
                    throw ReleaseIsCurrent::cannotYankWithoutRollback($release);
                }

                if ($rollbackTo->is($release)) {
                    throw InvalidPromotion::rollbackToSelf($release);
                }

                $this->promote->promote($rollbackTo->refresh(), $track);
                $movedTo = $rollbackTo;
            }

            $release->status = ReleaseStatus::Yanked;
            $release->yanked_at = now();
            $release->yank_reason = $reason !== null ? mb_substr($reason, 0, 255) : null;
            $release->save();

            ReleaseYanked::dispatch($release, $movedTo);

            return $release;
        });
    }
}
