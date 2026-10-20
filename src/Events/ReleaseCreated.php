<?php

namespace TecClub\ReleaseKit\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use TecClub\ReleaseKit\Models\Release;

/**
 * A draft release was created.
 */
class ReleaseCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Release $release,
    ) {}
}
