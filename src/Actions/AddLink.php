<?php

namespace TecClub\ReleaseKit\Actions;

use TecClub\ReleaseKit\Actions\Concerns\ValidatesInput;
use TecClub\ReleaseKit\Actions\Concerns\ValidatesLinks;
use TecClub\ReleaseKit\Enums\LinkType;
use TecClub\ReleaseKit\Exceptions\InvalidLink;
use TecClub\ReleaseKit\Models\Link;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Support\ModelResolver;

/**
 * Adds a store or external link.
 *
 * Without $release it is a permanent platform link; with one it belongs to
 * that release and takes its platform. Passing $store makes it a store link.
 */
class AddLink
{
    use ValidatesInput;
    use ValidatesLinks;

    public function handle(
        string $url,
        ?string $platform = null,
        ?Release $release = null,
        ?string $store = null,
        ?string $label = null,
        int $sortOrder = 0,
        bool $isActive = true,
    ): Link {
        if ($release !== null) {
            $this->assertContentsEditable($release);

            if ($platform !== null && $platform !== $release->platform) {
                throw InvalidLink::platformMismatch($platform, $release->platform);
            }

            $platform = $release->platform;
        }

        if ($platform === null) {
            throw InvalidLink::missingPlatform();
        }

        $this->assertPlatform($platform);
        $this->assertStore($platform, $store);
        $this->assertUrl($url);

        return ModelResolver::link()::query()->create([
            'platform' => $platform,
            'app_release_id' => $release?->getKey(),
            'type' => $store !== null ? LinkType::Store : LinkType::External,
            'store' => $store,
            'label' => $label,
            'url' => $url,
            'is_active' => $isActive,
            'sort_order' => $sortOrder,
        ]);
    }
}
