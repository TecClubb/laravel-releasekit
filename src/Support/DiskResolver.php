<?php

namespace TecClub\ReleaseKit\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * Resolves the storage disk: the channel's "disk", then "storage.disk",
 * then config('filesystems.default').
 */
class DiskResolver
{
    public function __construct(
        private readonly Repository $config,
        private readonly ChannelRegistry $channels,
        private readonly Factory $filesystem,
    ) {}

    public function name(?string $channel = null): string
    {
        if ($channel !== null && ($disk = $this->channels->disk($channel)) !== null) {
            return $disk;
        }

        $disk = $this->config->get('releasekit.storage.disk');

        if (is_string($disk) && $disk !== '') {
            return $disk;
        }

        return (string) $this->config->get('filesystems.default', 'local');
    }

    public function disk(?string $channel = null): Filesystem
    {
        return $this->filesystem->disk($this->name($channel));
    }

    public function directory(): string
    {
        return trim((string) $this->config->get('releasekit.storage.directory', 'releasekit'), '/');
    }
}
