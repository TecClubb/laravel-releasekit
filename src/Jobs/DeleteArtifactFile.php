<?php

namespace TecClub\ReleaseKit\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Removes an artifact's file from storage. Dispatched after the DB commit;
 * a failure only logs a warning and never affects the database.
 */
class DeleteArtifactFile implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public readonly string $disk,
        public readonly string $path,
    ) {}

    public function handle(Factory $filesystem): void
    {
        try {
            $disk = $filesystem->disk($this->disk);

            if ($disk->exists($this->path) && ! $disk->delete($this->path)) {
                Log::warning('ReleaseKit could not delete an artifact file.', ['disk' => $this->disk, 'path' => $this->path]);
            }
        } catch (Throwable $e) {
            Log::warning('ReleaseKit could not delete an artifact file.', [
                'disk' => $this->disk,
                'path' => $this->path,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
