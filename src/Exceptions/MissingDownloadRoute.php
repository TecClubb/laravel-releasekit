<?php

namespace TecClub\ReleaseKit\Exceptions;

class MissingDownloadRoute extends ReleaseKitException
{
    public static function named(string $name): self
    {
        return new self(
            "The artifact download route \"{$name}\" is not registered. Set releasekit.routes.register to true, "
            .'publish the route stub (--tag=releasekit-routes), or bind your own DownloadUrlGenerator.'
        );
    }
}
