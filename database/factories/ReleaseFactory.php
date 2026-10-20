<?php

namespace TecClub\ReleaseKit\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use TecClub\ReleaseKit\Enums\ReleaseStatus;
use TecClub\ReleaseKit\Models\Release;

/**
 * @extends Factory<Release>
 */
class ReleaseFactory extends Factory
{
    protected $model = Release::class;

    public function definition(): array
    {
        return [
            'platform' => 'android',
            'channel' => 'stable',
            'version' => '1.'.fake()->unique()->numberBetween(0, 99999).'.0',
            'build_number' => (string) fake()->numberBetween(1, 9999),
            'status' => ReleaseStatus::Draft,
            'release_notes' => fake()->sentence(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => ReleaseStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function yanked(?string $reason = null): static
    {
        return $this->state(fn () => [
            'status' => ReleaseStatus::Yanked,
            'published_at' => now()->subDay(),
            'yanked_at' => now(),
            'yank_reason' => $reason,
        ]);
    }
}
