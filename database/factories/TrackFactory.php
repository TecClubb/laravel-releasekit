<?php

namespace TecClub\ReleaseKit\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Models\Track;

/**
 * @extends Factory<Track>
 */
class TrackFactory extends Factory
{
    protected $model = Track::class;

    public function definition(): array
    {
        return [
            'platform' => 'android',
            'channel' => 'stable',
            'current_release_id' => null,
            'minimum_supported_version' => null,
        ];
    }

    /**
     * Points the track at the release, matching its platform and channel.
     */
    public function current(Release $release): static
    {
        return $this->state(fn () => [
            'platform' => $release->platform,
            'channel' => $release->channel,
            'current_release_id' => $release->getKey(),
        ]);
    }
}
