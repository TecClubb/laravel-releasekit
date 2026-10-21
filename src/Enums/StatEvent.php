<?php

namespace TecClub\ReleaseKit\Enums;

enum StatEvent: string
{
    /** A client called the version check endpoint. */
    case Check = 'check';

    /** A download was initiated through the app's download endpoint. */
    case Download = 'download';
}
