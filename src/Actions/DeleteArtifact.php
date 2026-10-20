<?php

namespace TecClub\ReleaseKit\Actions;

use TecClub\ReleaseKit\Actions\Concerns\RunsInTransaction;
use TecClub\ReleaseKit\Jobs\DeleteArtifactFile;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Support\Concerns\ValidatesInput;

/**
 * Deletes an artifact. Its file is removed by a queued job after the
 * DB commit; a failed file delete never blocks this.
 */
class DeleteArtifact
{
    use RunsInTransaction;
    use ValidatesInput;

    public function handle(Artifact $artifact): void
    {
        $this->assertContentsEditable($artifact->release);

        $this->transaction(function () use ($artifact) {
            $artifact->delete();

            DeleteArtifactFile::dispatch($artifact->disk, $artifact->path)->afterCommit();
        });
    }
}
