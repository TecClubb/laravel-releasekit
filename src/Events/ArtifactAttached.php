<?php

namespace TecClub\ReleaseKit\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use TecClub\ReleaseKit\Models\Artifact;

/**
 * A build file was attached to a release.
 */
class ArtifactAttached implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Artifact $artifact,
    ) {}
}
