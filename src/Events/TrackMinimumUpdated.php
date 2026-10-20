<?php

namespace TecClub\ReleaseKit\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use TecClub\ReleaseKit\Models\Track;

/**
 * A track's minimum supported version changed.
 */
class TrackMinimumUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Track $track,
        public readonly ?string $previousMinimum,
    ) {}
}
