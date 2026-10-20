<?php

namespace TecClub\ReleaseKit\Exceptions;

class InvalidLink extends ReleaseKitException
{
    public static function url(string $url): self
    {
        return new self("The link URL \"{$url}\" is not a valid URL.");
    }

    public static function missingPlatform(): self
    {
        return new self('A permanent link needs a platform.');
    }

    public static function platformMismatch(string $platform, string $releasePlatform): self
    {
        return new self("The link platform \"{$platform}\" does not match the release platform \"{$releasePlatform}\".");
    }
}
