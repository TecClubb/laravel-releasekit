<?php

namespace TecClub\ReleaseKit\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use TecClub\ReleaseKit\Models\Release;

/**
 * The public view of a release.
 *
 * @implements Arrayable<string, string|null>
 */
final readonly class ReleaseSummary implements Arrayable, JsonSerializable
{
    public function __construct(
        public int $id,
        public string $version,
        public ?string $buildNumber,
        public ?string $releaseNotes,
        public ?string $publishedAt,
    ) {}

    public static function fromModel(Release $release): self
    {
        return new self(
            id: (int) $release->getKey(),
            version: $release->version,
            buildNumber: $release->build_number,
            releaseNotes: $release->release_notes,
            publishedAt: $release->published_at?->toImmutable()->utc()->format('Y-m-d\TH:i:s\Z'),
        );
    }

    /**
     * @param  array{id: int, version: string, build_number: ?string, release_notes: ?string, published_at: ?string}  $data
     */
    public static function fromCache(array $data): self
    {
        return new self($data['id'], $data['version'], $data['build_number'], $data['release_notes'], $data['published_at']);
    }

    /**
     * @return array{id: int, version: string, build_number: ?string, release_notes: ?string, published_at: ?string}
     */
    public function toCache(): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'build_number' => $this->buildNumber,
            'release_notes' => $this->releaseNotes,
            'published_at' => $this->publishedAt,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(bool $withNotes = true): array
    {
        return array_filter([
            'version' => $this->version,
            'build_number' => $this->buildNumber,
            'release_notes' => $this->releaseNotes,
            'published_at' => $this->publishedAt,
        ], fn ($value, $key) => $withNotes || $key !== 'release_notes', ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @return array<string, string|null>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
