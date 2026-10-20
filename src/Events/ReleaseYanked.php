<?php

namespace TecClub\ReleaseKit\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use TecClub\ReleaseKit\Models\Release;

/**
 * A release was yanked. $rollbackTo is set when the track was moved to it.
 */
class ReleaseYanked implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Release $release,
        public readonly ?Release $rollbackTo,
    ) {}
}
