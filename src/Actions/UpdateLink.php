<?php

namespace TecClub\ReleaseKit\Actions;

use TecClub\ReleaseKit\Actions\Concerns\ValidatesInput;
use TecClub\ReleaseKit\Actions\Concerns\ValidatesLinks;
use TecClub\ReleaseKit\Enums\LinkType;
use TecClub\ReleaseKit\Models\Link;

/**
 * Updates a link. Its platform and release are fixed. Setting a store
 * makes it a store link; clearing the store makes it external.
 */
class UpdateLink
{
    use ValidatesInput;
    use ValidatesLinks;

    public const EDITABLE = ['url', 'store', 'label', 'is_active', 'sort_order'];

    /**
     * @param  array<string, mixed>  $attributes  Keys: url, store, label, is_active, sort_order
     */
    public function handle(Link $link, array $attributes): Link
    {
        $this->assertKnownAttributes($attributes, self::EDITABLE);
        $this->assertContentsEditable($link->release);

        if (array_key_exists('store', $attributes)) {
            $this->assertStore($link->platform, $attributes['store']);
            $attributes['type'] = $attributes['store'] !== null ? LinkType::Store : LinkType::External;
        }

        if (array_key_exists('url', $attributes)) {
            $this->assertUrl((string) $attributes['url']);
        }

        $link->fill($attributes)->save();

        return $link;
    }
}
