<?php

namespace TecClub\ReleaseKit\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Factory;
use TecClub\ReleaseKit\Support\ChannelRegistry;
use TecClub\ReleaseKit\Support\DiskResolver;
use TecClub\ReleaseKit\Support\ModelResolver;
use Throwable;

/**
 * Finds files under the ReleaseKit storage directory that no artifact row
 * references, e.g. after a failed delete job or an interrupted upload.
 *
 * Files modified within the grace period are skipped, so a file that is
 * being attached right now (moved into place, row not yet committed) is
 * never touched.
 */
class PruneOrphansCommand extends Command
{
    protected $signature = 'releasekit:prune-orphans
        {--dry-run : List orphaned files without deleting them}
        {--disk=* : Only scan these disks (default: every disk ReleaseKit uses)}
        {--older-than=60 : Skip files modified within this many minutes}
        {--force : Delete without asking for confirmation}';

    protected $description = 'List or delete ReleaseKit files that have no artifact row';

    public function handle(Factory $filesystem, DiskResolver $disks, ChannelRegistry $channels): int
    {
        $directory = $disks->directory();
        $cutoff = now()->subMinutes(max(0, (int) $this->option('older-than')))->getTimestamp();

        $orphans = [];

        foreach ($this->disksToScan($disks, $channels) as $diskName) {
            $disk = $filesystem->disk($diskName);
            $known = array_flip(
                ModelResolver::artifact()::query()->where('disk', $diskName)->pluck('path')->all()
            );

            foreach ($disk->allFiles($directory) as $path) {
                if (isset($known[$path])) {
                    continue;
                }

                try {
                    if ($disk->lastModified($path) > $cutoff) {
                        continue;
                    }

                    $size = $disk->size($path);
                } catch (Throwable) {
                    continue; // Vanished while scanning.
                }

                $orphans[] = ['disk' => $diskName, 'path' => $path, 'size' => $size];
            }
        }

        if ($orphans === []) {
            $this->components->info('No orphaned files found.');

            return self::SUCCESS;
        }

        $totalBytes = array_sum(array_column($orphans, 'size'));

        $this->table(
            ['Disk', 'Path', 'Size'],
            array_map(fn (array $orphan) => [$orphan['disk'], $orphan['path'], $this->humanSize($orphan['size'])], $orphans),
        );

        $summary = count($orphans).' orphaned file(s), '.$this->humanSize($totalBytes).'.';

        if ($this->option('dry-run')) {
            $this->components->info("{$summary} Dry run: nothing was deleted.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Delete {$summary}", false)) {
            $this->components->warn('Nothing was deleted.');

            return self::SUCCESS;
        }

        $deleted = 0;

        foreach ($orphans as $orphan) {
            try {
                $filesystem->disk($orphan['disk'])->delete($orphan['path']) && $deleted++;
            } catch (Throwable $e) {
                $this->components->warn("Could not delete {$orphan['disk']}:{$orphan['path']} ({$e->getMessage()})");
            }
        }

        $this->components->info("Deleted {$deleted} of ".count($orphans).' orphaned file(s).');

        return $deleted === count($orphans) ? self::SUCCESS : self::FAILURE;
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;

        return round($bytes / (1024 ** $power), 1).' '.$units[$power];
    }

    /**
     * @return list<string>
     */
    private function disksToScan(DiskResolver $disks, ChannelRegistry $channels): array
    {
        $requested = array_filter((array) $this->option('disk'));

        if ($requested !== []) {
            return array_values(array_unique($requested));
        }

        $names = [$disks->name()];

        foreach ($channels->keys() as $channel) {
            $names[] = $disks->name($channel);
        }

        $names = array_merge($names, ModelResolver::artifact()::query()->distinct()->pluck('disk')->all());

        return array_values(array_unique($names));
    }
}
