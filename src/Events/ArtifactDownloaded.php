<?php

namespace TecClub\ReleaseKit\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use TecClub\ReleaseKit\Enums\DeliveryMode;
use TecClub\ReleaseKit\Models\Artifact;

/**
 * A download was initiated through the app's download endpoint. This is
 * an initiated download, not a confirmed one. Downloads served straight
 * from storage (public mode without the app) never fire it.
 */
class ArtifactDownloaded implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Artifact $artifact,
        public readonly DeliveryMode $mode,
    ) {}
}
