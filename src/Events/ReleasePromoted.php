<?php

namespace TecClub\ReleaseKit\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Models\Track;

/**
 * A release became the current release of its track.
 */
class ReleasePromoted implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Release $release,
        public readonly Track $track,
        public readonly ?Release $previous,
    ) {}
}
