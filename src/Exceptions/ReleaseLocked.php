<?php

namespace TecClub\ReleaseKit\Exceptions;

use TecClub\ReleaseKit\Models\Release;

/**
 * A change that the release's status does not allow.
 */
class ReleaseLocked extends ReleaseKitException
{
    /**
     * @param  list<string>  $fields
     */
    public static function fields(Release $release, array $fields): self
    {
        $list = implode(', ', $fields);

        return new self("{$release->displayName()} is {$release->status->value}; these fields can no longer be changed: {$list}.");
    }

    public static function contents(Release $release): self
    {
        return new self("{$release->displayName()} is yanked; its artifacts and links can no longer be changed.");
    }

    public static function notDeletable(Release $release): self
    {
        return new self("{$release->displayName()} is {$release->status->value} and cannot be deleted. Only drafts can be deleted; yank published releases instead.");
    }

    public static function hasContents(Release $release, string $field): self
    {
        return new self("The {$field} of {$release->displayName()} cannot change while it has artifacts or links. Remove them first.");
    }
}
