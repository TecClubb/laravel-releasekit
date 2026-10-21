<?php

namespace TecClub\ReleaseKit\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use TecClub\ReleaseKit\Actions\Concerns\RunsInTransaction;
use TecClub\ReleaseKit\Enums\ReleaseStatus;
use TecClub\ReleaseKit\Events\ReleaseCreated;
use TecClub\ReleaseKit\Exceptions\DuplicateRelease;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Support\ChannelRegistry;
use TecClub\ReleaseKit\Support\Concerns\ValidatesInput;
use TecClub\ReleaseKit\Support\ModelResolver;
use TecClub\ReleaseKit\Support\VersionComparator;

class CreateRelease
{
    use RunsInTransaction;
    use ValidatesInput;

    /**
     * Creates a draft release. The channel defaults to releasekit.default_channel.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function handle(
        string $platform,
        string $version,
        ?string $channel = null,
        ?string $buildNumber = null,
        ?string $releaseNotes = null,
        ?array $metadata = null,
    ): Release {
        $channel ??= app(ChannelRegistry::class)->default();

        $this->assertPlatform($platform);
        $this->assertChannel($channel);
        VersionComparator::assertValid($version);

        return $this->transaction(function () use ($platform, $version, $channel, $buildNumber, $releaseNotes, $metadata) {
            $this->assertVersionIsFree($platform, $channel, $version);

            try {
                $release = ModelResolver::release()::query()->create([
                    'platform' => $platform,
                    'channel' => $channel,
                    'version' => $version,
                    'build_number' => $buildNumber,
                    'status' => ReleaseStatus::Draft,
                    'release_notes' => $releaseNotes,
                    'metadata' => $metadata,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw DuplicateRelease::make($platform, $channel, $version);
            }

            ReleaseCreated::dispatch($release);

            return $release;
        });
    }
}
