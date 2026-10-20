<?php

namespace TecClub\ReleaseKit\Data;

/**
 * Everything about one platform + channel that does not depend on the
 * client. This is what ReleaseCache stores (as a plain array), so a check
 * costs zero queries once warm.
 *
 * Artifacts are every active artifact of the current release; format and
 * architecture filtering happens at read time so config changes apply
 * immediately. Links are the current release's active links followed by
 * the platform's active permanent links.
 */
final readonly class TrackSnapshot
{
    /**
     * @param  list<string>  $yankedVersions
     * @param  list<ArtifactData>  $artifacts
     * @param  list<LinkData>  $links
     */
    public function __construct(
        public string $platform,
        public string $channel,
        public ?ReleaseSummary $current,
        public ?string $minimumSupportedVersion,
        public array $yankedVersions,
        public array $artifacts,
        public array $links,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromCache(array $data): self
    {
        return new self(
            platform: $data['platform'],
            channel: $data['channel'],
            current: $data['current'] !== null ? ReleaseSummary::fromCache($data['current']) : null,
            minimumSupportedVersion: $data['minimum_supported_version'],
            yankedVersions: $data['yanked_versions'],
            artifacts: array_map(ArtifactData::fromCache(...), $data['artifacts']),
            links: array_map(LinkData::fromCache(...), $data['links']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toCache(): array
    {
        return [
            'platform' => $this->platform,
            'channel' => $this->channel,
            'current' => $this->current?->toCache(),
            'minimum_supported_version' => $this->minimumSupportedVersion,
            'yanked_versions' => $this->yankedVersions,
            'artifacts' => array_map(fn (ArtifactData $artifact) => $artifact->toCache(), $this->artifacts),
            'links' => array_map(fn (LinkData $link) => $link->toCache(), $this->links),
        ];
    }
}
