<?php

namespace TecClub\ReleaseKit\Actions;

use TecClub\ReleaseKit\Actions\Concerns\RunsInTransaction;
use TecClub\ReleaseKit\Exceptions\ReleaseLocked;
use TecClub\ReleaseKit\Models\Release;

/**
 * Deletes a draft with its artifacts and links. Artifact files are queued
 * for deletion after commit. Published and yanked releases are never
 * deleted; yank them instead.
 */
class DeleteDraftRelease
{
    use RunsInTransaction;

    public function __construct(
        private readonly DeleteArtifact $deleteArtifact,
        private readonly DeleteLink $deleteLink,
    ) {}

    public function handle(Release $release): void
    {
        $this->transaction(function () use ($release) {
            $release->refresh();

            if (! $release->isDraft()) {
                throw ReleaseLocked::notDeletable($release);
            }

            // Deleted one by one (not by FK cascade) so file cleanup and observers run.
            $release->artifacts()->get()->each(fn ($artifact) => $this->deleteArtifact->handle($artifact));
            $release->links()->get()->each(fn ($link) => $this->deleteLink->handle($link));

            $release->delete();
        });
    }
}
