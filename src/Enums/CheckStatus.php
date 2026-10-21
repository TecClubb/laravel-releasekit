<?php

namespace TecClub\ReleaseKit\Enums;

/**
 * Result of a version check, evaluated in this order; the first match wins.
 */
enum CheckStatus: string
{
    /** The client runs a yanked version. */
    case Blocked = 'blocked';

    /** The client is below the track's minimum supported version. */
    case UpdateRequired = 'update_required';

    /** The client is below the current release. */
    case UpdateAvailable = 'update_available';

    /** Anything else, including dev builds newer than current. */
    case UpToDate = 'up_to_date';
}
