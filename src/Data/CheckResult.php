<?php

namespace TecClub\ReleaseKit\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use TecClub\ReleaseKit\Enums\CheckStatus;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class CheckResult implements Arrayable, JsonSerializable
{
    public function __construct(
        public CheckStatus $status,
        public string $clientVersion,
        public ?string $minimumSupportedVersion,
        public ?ReleaseSummary $current,
        public PlatformDownloads $downloads,
    ) {}

    /**
     * The client must update before it can continue (blocked or below minimum).
     */
    public function mustUpdate(): bool
    {
        return in_array($this->status, [CheckStatus::Blocked, CheckStatus::UpdateRequired], true);
    }

    public function canUpdate(): bool
    {
        return $this->status !== CheckStatus::UpToDate;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'client_version' => $this->clientVersion,
            'minimum_supported_version' => $this->minimumSupportedVersion,
            'current' => $this->current?->toArray(),
            'downloads' => [
                'artifacts' => array_map(fn (ArtifactDownload $artifact) => $artifact->toArray(), $this->downloads->artifacts),
                'links' => array_map(fn (LinkData $link) => $link->toArray(), $this->downloads->links),
            ],
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
