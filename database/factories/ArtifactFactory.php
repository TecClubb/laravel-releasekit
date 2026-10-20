<?php

namespace TecClub\ReleaseKit\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Release;

/**
 * @extends Factory<Artifact>
 */
class ArtifactFactory extends Factory
{
    protected $model = Artifact::class;

    public function definition(): array
    {
        $filename = 'app-'.Str::lower(Str::random(6)).'.apk';

        return [
            'app_release_id' => Release::factory(),
            'architecture' => null,
            'format' => 'apk',
            'label' => null,
            'disk' => 'local',
            'path' => 'releasekit/stable/android/'.Str::ulid().'-'.$filename,
            'original_filename' => $filename,
            'mime_type' => 'application/vnd.android.package-archive',
            'size_bytes' => fake()->numberBetween(1_000_000, 90_000_000),
            'checksum_sha256' => hash('sha256', $filename),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function architecture(?string $architecture): static
    {
        return $this->state(fn () => ['architecture' => $architecture]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
