<?php

namespace TecClub\ReleaseKit\Exceptions;

use TecClub\ReleaseKit\Enums\ReleaseStatus;
use TecClub\ReleaseKit\Models\Release;

/**
 * Allowed transitions: draft → published, published → yanked, yanked → published.
 */
class InvalidStatusTransition extends ReleaseKitException
{
    public static function to(Release $release, ReleaseStatus $target): self
    {
        return new self("{$release->displayName()} is {$release->status->value} and cannot be changed to {$target->value}.");
    }
}
