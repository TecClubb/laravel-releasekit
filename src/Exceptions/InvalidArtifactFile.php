<?php

namespace TecClub\ReleaseKit\Exceptions;

class InvalidArtifactFile extends ReleaseKitException
{
    public static function missing(string $disk, string $path): self
    {
        return new self("The file \"{$path}\" does not exist on the \"{$disk}\" disk.");
    }

    public static function tooLarge(int $bytes, int $maxMegabytes): self
    {
        $megabytes = round($bytes / 1024 / 1024, 1);

        return new self("The file is {$megabytes} MB; the maximum upload size is {$maxMegabytes} MB.");
    }

    public static function unreadable(string $filename): self
    {
        return new self("The uploaded file \"{$filename}\" could not be read.");
    }
}
