<?php

namespace TecClub\ReleaseKit\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * An artifact as offered to clients, with its download URL.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class ArtifactDownload implements Arrayable, JsonSerializable
{
    public function __construct(
        public ArtifactData $artifact,
        public string $url,
    ) {}

    /**
     * @return array{id: int, architecture: ?string, format: string, label: ?string, size_bytes: ?int, checksum_sha256: ?string, url: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->artifact->id,
            'architecture' => $this->artifact->architecture,
            'format' => $this->artifact->format,
            'label' => $this->artifact->label,
            'size_bytes' => $this->artifact->sizeBytes,
            'checksum_sha256' => $this->artifact->checksumSha256,
            'url' => $this->url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
