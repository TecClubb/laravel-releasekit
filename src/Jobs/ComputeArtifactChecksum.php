<?php

namespace TecClub\ReleaseKit\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use TecClub\ReleaseKit\Support\ModelResolver;
use Throwable;

/**
 * Streams an artifact's file and fills in checksum_sha256, size_bytes and
 * mime_type where they are missing. Values that are already set are kept.
 *
 * The file is read in 1 MB chunks, so memory stays flat for multi-GB builds.
 */
class ComputeArtifactChecksum implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    private const CHUNK_BYTES = 1024 * 1024;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public readonly int $artifactId) {}

    public function handle(Factory $filesystem): void
    {
        $artifact = ModelResolver::artifact()::query()->find($this->artifactId);

        // Deleted before the job ran.
        if ($artifact === null) {
            return;
        }

        if ($artifact->checksum_sha256 !== null && $artifact->size_bytes !== null && $artifact->mime_type !== null) {
            return;
        }

        $disk = $filesystem->disk($artifact->disk);

        if (! $disk->exists($artifact->path)) {
            Log::warning('ReleaseKit could not compute a checksum: the artifact file is missing.', [
                'artifact' => $artifact->getKey(),
                'disk' => $artifact->disk,
                'path' => $artifact->path,
            ]);

            return;
        }

        $updates = [];

        if ($artifact->checksum_sha256 === null || $artifact->size_bytes === null) {
            [$checksum, $size] = $this->hash($disk->readStream($artifact->path));

            $updates['checksum_sha256'] = $artifact->checksum_sha256 ?? $checksum;
            $updates['size_bytes'] = $artifact->size_bytes ?? $size;
        }

        if ($artifact->mime_type === null) {
            try {
                $updates['mime_type'] = $disk->mimeType($artifact->path) ?: null;
            } catch (Throwable) {
                // Some drivers can't tell; leave it empty.
            }
        }

        // A normal save, so the cache observer refreshes the served checksum.
        $artifact->forceFill($updates)->save();
    }

    /**
     * @param  resource|null  $stream
     * @return array{string, int}
     */
    private function hash($stream): array
    {
        if (! is_resource($stream)) {
            throw new RuntimeException("Artifact {$this->artifactId} could not be opened for reading.");
        }

        $context = hash_init('sha256');
        $size = 0;

        try {
            while (! feof($stream)) {
                $chunk = fread($stream, self::CHUNK_BYTES);

                if ($chunk === false) {
                    throw new RuntimeException("Artifact {$this->artifactId} could not be read.");
                }

                hash_update($context, $chunk);
                $size += strlen($chunk);
            }
        } finally {
            fclose($stream);
        }

        return [hash_final($context), $size];
    }
}
