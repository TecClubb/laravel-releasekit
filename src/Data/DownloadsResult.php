<?php

namespace TecClub\ReleaseKit\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class DownloadsResult implements Arrayable, JsonSerializable
{
    /**
     * @param  array<string, PlatformDownloads>  $platforms  Keyed by platform; platforms with nothing to offer are omitted.
     */
    public function __construct(
        public string $channel,
        public array $platforms,
    ) {}

    public function platform(string $platform): ?PlatformDownloads
    {
        return $this->platforms[$platform] ?? null;
    }

    /**
     * @return array{channel: string, platforms: array<string, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'channel' => $this->channel,
            'platforms' => array_map(fn (PlatformDownloads $downloads) => $downloads->toArray(), $this->platforms),
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
