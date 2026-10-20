<?php

namespace TecClub\ReleaseKit\Exceptions;

use TecClub\ReleaseKit\Models\Release;

class ReleaseIsCurrent extends ReleaseKitException
{
    public static function cannotYankWithoutRollback(Release $release): self
    {
        return new self("{$release->displayName()} is the current release. Pass a rollback target to yank it.");
    }
}
