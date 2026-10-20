<?php

namespace TecClub\ReleaseKit\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * What one platform offers on a channel: the current release, its
 * downloadable artifacts and the applicable links.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class PlatformDownloads implements Arrayable, JsonSerializable
{
    /**
     * @param  list<ArtifactDownload>  $artifacts
     * @param  list<LinkData>  $links
     */
    public function __construct(
        public string $platform,
        public string $label,
        public ?ReleaseSummary $current,
        public array $artifacts,
        public array $links,
    ) {}

    /**
     * Nothing to download and no link to follow.
     */
    public function isEmpty(): bool
    {
        return $this->artifacts === [] && $this->links === [];
    }

    public function primaryArtifact(): ?ArtifactDownload
    {
        return $this->artifacts[0] ?? null;
    }

    public function primaryLink(): ?LinkData
    {
        return $this->links[0] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'current' => $this->current?->toArray(withNotes: false),
            'artifacts' => array_map(fn (ArtifactDownload $artifact) => $artifact->toArray(), $this->artifacts),
            'links' => array_map(fn (LinkData $link) => $link->toArray(), $this->links),
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
