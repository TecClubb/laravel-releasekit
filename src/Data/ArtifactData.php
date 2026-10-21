<?php

namespace TecClub\ReleaseKit\Data;

use TecClub\ReleaseKit\Models\Artifact;

/**
 * Server-side snapshot of an artifact. Holds the storage location, so it is
 * never serialised to clients directly; see ArtifactDownload.
 */
final readonly class ArtifactData
{
    public function __construct(
        public int $id,
        public ?string $architecture,
        public string $format,
        public ?string $label,
        public string $disk,
        public string $path,
        public string $originalFilename,
        public ?string $mimeType,
        public ?int $sizeBytes,
        public ?string $checksumSha256,
        public int $sortOrder,
    ) {}

    public static function fromModel(Artifact $artifact): self
    {
        return new self(
            id: (int) $artifact->getKey(),
            architecture: $artifact->architecture,
            format: $artifact->format,
            label: $artifact->label,
            disk: $artifact->disk,
            path: $artifact->path,
            originalFilename: $artifact->original_filename,
            mimeType: $artifact->mime_type,
            sizeBytes: $artifact->size_bytes,
            checksumSha256: $artifact->checksum_sha256,
            sortOrder: $artifact->sort_order,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromCache(array $data): self
    {
        return new self(...$data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toCache(): array
    {
        return get_object_vars($this);
    }
}
