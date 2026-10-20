<?php

namespace TecClub\ReleaseKit\Actions;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use TecClub\ReleaseKit\Actions\Concerns\RunsInTransaction;
use TecClub\ReleaseKit\Events\ArtifactAttached;
use TecClub\ReleaseKit\Exceptions\InvalidArtifactFile;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Support\Concerns\ValidatesInput;
use TecClub\ReleaseKit\Support\DiskResolver;
use TecClub\ReleaseKit\Support\ModelResolver;
use Throwable;

/**
 * Attaches a build file to a draft or published release.
 *
 * $file is either an UploadedFile (streamed to the disk, never read into
 * memory) or a path that already exists on the release's disk, e.g. after
 * a direct-to-storage upload. That file is moved to its final location,
 * which is a server-side copy on S3 and R2.
 *
 * Final path: {directory}/{channel}/{platform}/{version}/{ulid}-{filename}
 */
class AttachArtifact
{
    use RunsInTransaction;
    use ValidatesInput;

    public function __construct(private readonly DiskResolver $disks) {}

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function handle(
        Release $release,
        UploadedFile|string $file,
        string $format,
        ?string $architecture = null,
        ?string $label = null,
        int $sortOrder = 0,
        ?array $metadata = null,
    ): Artifact {
        $this->assertContentsEditable($release);
        $this->assertFormat($release->platform, $format);
        $this->assertArchitecture($release->platform, $architecture);

        $diskName = $this->disks->name($release->channel);
        $disk = $this->disks->disk($release->channel);

        $filename = $this->originalFilename($file);
        $size = $this->size($disk, $diskName, $file);
        $this->assertSize($size);

        $path = $this->targetPath($release, $filename);
        $mimeType = $this->store($disk, $file, $path, $filename);

        try {
            return $this->transaction(function () use ($release, $format, $architecture, $label, $sortOrder, $metadata, $diskName, $path, $filename, $mimeType, $size) {
                $artifact = ModelResolver::artifact()::query()->create([
                    'app_release_id' => $release->getKey(),
                    'architecture' => $architecture,
                    'format' => $format,
                    'label' => $label,
                    'disk' => $diskName,
                    'path' => $path,
                    'original_filename' => $filename,
                    'mime_type' => $mimeType,
                    'size_bytes' => $size,
                    'sort_order' => $sortOrder,
                    'metadata' => $metadata,
                ]);

                ArtifactAttached::dispatch($artifact);

                return $artifact;
            });
        } catch (Throwable $e) {
            $this->rollbackFile($disk, $file, $path);

            throw $e;
        }
    }

    private function originalFilename(UploadedFile|string $file): string
    {
        $name = $file instanceof UploadedFile ? $file->getClientOriginalName() : $file;
        $name = basename(str_replace('\\', '/', $name));

        return Str::limit($name !== '' ? $name : 'artifact', 255, '');
    }

    private function size(Filesystem $disk, string $diskName, UploadedFile|string $file): int
    {
        if ($file instanceof UploadedFile) {
            return (int) $file->getSize();
        }

        if (! $disk->exists($file)) {
            throw InvalidArtifactFile::missing($diskName, $file);
        }

        return $disk->size($file);
    }

    private function assertSize(int $bytes): void
    {
        $maxMegabytes = (int) config('releasekit.storage.max_upload_size_mb', 2048);

        if ($maxMegabytes > 0 && $bytes > $maxMegabytes * 1024 * 1024) {
            throw InvalidArtifactFile::tooLarge($bytes, $maxMegabytes);
        }
    }

    private function targetPath(Release $release, string $filename): string
    {
        // Keep storage keys URL- and shell-safe; the original name is kept for Content-Disposition.
        $extension = Str::endsWith(Str::lower($filename), '.tar.gz') ? 'tar.gz' : pathinfo($filename, PATHINFO_EXTENSION);
        $base = $extension === '' ? $filename : substr($filename, 0, -(strlen($extension) + 1));
        $safe = Str::limit(Str::slug($base) ?: 'artifact', 150, '').($extension !== '' ? '.'.Str::lower($extension) : '');

        return implode('/', [
            $this->disks->directory(),
            $release->channel,
            $release->platform,
            $release->version,
            Str::lower((string) Str::ulid()).'-'.$safe,
        ]);
    }

    /**
     * Writes the file to its final path and returns its MIME type, if known.
     */
    private function store(Filesystem $disk, UploadedFile|string $file, string $path, string $filename): ?string
    {
        if ($file instanceof UploadedFile) {
            // putFileAs streams from disk rather than loading the file into memory.
            if ($disk->putFileAs(dirname($path), $file, basename($path)) === false) {
                throw InvalidArtifactFile::unreadable($filename);
            }

            return $file->getMimeType() ?? $file->getClientMimeType();
        }

        $disk->move($file, $path);

        try {
            return $disk->mimeType($path) ?: null;
        } catch (Throwable) {
            return null; // filled in later by the checksum job
        }
    }

    private function rollbackFile(Filesystem $disk, UploadedFile|string $file, string $path): void
    {
        try {
            $file instanceof UploadedFile ? $disk->delete($path) : $disk->move($path, $file);
        } catch (Throwable) {
            // The prune command cleans up anything left behind.
        }
    }
}
