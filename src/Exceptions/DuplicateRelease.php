<?php

namespace TecClub\ReleaseKit\Exceptions;

class DuplicateRelease extends ReleaseKitException
{
    public static function make(string $platform, string $channel, string $version): self
    {
        return new self("A release {$version} already exists for {$platform} on the {$channel} channel.");
    }
}
