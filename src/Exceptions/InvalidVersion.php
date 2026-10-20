<?php

namespace TecClub\ReleaseKit\Exceptions;

class InvalidVersion extends ReleaseKitException
{
    public static function format(string $version): self
    {
        return new self("The version \"{$version}\" is invalid. Use 2 to 4 numeric parts, e.g. 1.2 or 1.2.0.");
    }
}
