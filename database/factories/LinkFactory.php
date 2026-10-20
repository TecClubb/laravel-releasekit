<?php

namespace TecClub\ReleaseKit\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use TecClub\ReleaseKit\Enums\LinkType;
use TecClub\ReleaseKit\Models\Link;
use TecClub\ReleaseKit\Models\Release;

/**
 * @extends Factory<Link>
 */
class LinkFactory extends Factory
{
    protected $model = Link::class;

    public function definition(): array
    {
        return [
            'platform' => 'android',
            'app_release_id' => null,
            'type' => LinkType::Store,
            'store' => 'play_store',
            'label' => null,
            'url' => 'https://play.google.com/store/apps/details?id=com.example.app',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function external(string $url = 'https://example.com/download'): static
    {
        return $this->state(fn () => [
            'type' => LinkType::External,
            'store' => null,
            'url' => $url,
        ]);
    }

    public function forRelease(Release $release): static
    {
        return $this->state(fn () => [
            'platform' => $release->platform,
            'app_release_id' => $release->getKey(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
