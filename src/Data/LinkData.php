<?php

namespace TecClub\ReleaseKit\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use TecClub\ReleaseKit\Models\Link;

/**
 * @implements Arrayable<string, string|null>
 */
final readonly class LinkData implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $type,
        public ?string $store,
        public ?string $label,
        public string $url,
        public int $sortOrder,
        public bool $permanent,
    ) {}

    public static function fromModel(Link $link): self
    {
        return new self(
            type: $link->type->value,
            store: $link->store,
            label: $link->displayLabel(),
            url: $link->url,
            sortOrder: $link->sort_order,
            permanent: $link->isPermanent(),
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

    /**
     * @return array{type: string, store: ?string, label: ?string, url: string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'store' => $this->store,
            'label' => $this->label,
            'url' => $this->url,
        ];
    }

    /**
     * @return array{type: string, store: ?string, label: ?string, url: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
