<?php

namespace TecClub\ReleaseKit\Exceptions;

/**
 * A platform, channel, format, architecture or store that is not in config.
 */
class UnsupportedValue extends ReleaseKitException
{
    public static function platform(string $platform): self
    {
        return new self("The platform \"{$platform}\" is not configured.");
    }

    public static function channel(string $channel): self
    {
        return new self("The channel \"{$channel}\" is not configured.");
    }

    public static function format(string $platform, string $format): self
    {
        return new self("The format \"{$format}\" is not allowed for the platform \"{$platform}\".");
    }

    public static function architecture(string $platform, string $architecture): self
    {
        return new self("The architecture \"{$architecture}\" is not allowed for the platform \"{$platform}\".");
    }

    public static function store(string $platform, string $store): self
    {
        return new self("The store \"{$store}\" is not configured for the platform \"{$platform}\".");
    }
}
