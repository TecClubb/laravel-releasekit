<?php

namespace TecClub\ReleaseKit\Actions;

use TecClub\ReleaseKit\Actions\Concerns\RunsInTransaction;
use TecClub\ReleaseKit\Enums\ReleaseStatus;
use TecClub\ReleaseKit\Exceptions\ReleaseKitException;
use TecClub\ReleaseKit\Exceptions\ReleaseLocked;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Support\Concerns\ValidatesInput;
use TecClub\ReleaseKit\Support\VersionComparator;

/**
 * Updates release attributes within what its status allows:
 * drafts are fully editable, published releases accept notes and
 * metadata, yanked releases accept notes only.
 *
 * Unchanged values are ignored, so a host form can submit every field.
 */
class UpdateRelease
{
    use RunsInTransaction;
    use ValidatesInput;

    public const EDITABLE = [
        'draft' => ['platform', 'channel', 'version', 'build_number', 'release_notes', 'metadata'],
        'published' => ['release_notes', 'metadata'],
        'yanked' => ['release_notes'],
    ];

    /**
     * @param  array<string, mixed>  $attributes  Keys: platform, channel, version, build_number, release_notes, metadata
     */
    public function handle(Release $release, array $attributes): Release
    {
        $this->assertKnownAttributes($attributes, self::EDITABLE[ReleaseStatus::Draft->value]);

        return $this->transaction(function () use ($release, $attributes) {
            $release->refresh();
            $release->fill($attributes);

            try {
                $this->validate($release);
            } catch (ReleaseKitException $e) {
                $release->setRawAttributes($release->getRawOriginal());

                throw $e;
            }

            $release->save();

            return $release;
        });
    }

    private function validate(Release $release): void
    {
        $changed = array_keys($release->getDirty());
        $locked = array_values(array_diff($changed, self::EDITABLE[$release->status->value]));

        if ($locked !== []) {
            throw ReleaseLocked::fields($release, $locked);
        }

        if ($release->isDirty('platform')) {
            $this->assertPlatform($release->platform);

            if ($release->artifacts()->exists() || $release->links()->exists()) {
                throw ReleaseLocked::hasContents($release, 'platform');
            }
        }

        if ($release->isDirty('channel')) {
            $this->assertChannel($release->channel);

            // Artifacts live on the disk resolved for their channel.
            if ($release->artifacts()->exists()) {
                throw ReleaseLocked::hasContents($release, 'channel');
            }
        }

        if ($release->isDirty('version')) {
            VersionComparator::assertValid($release->version);
        }

        if ($release->isDirty(['platform', 'channel', 'version'])) {
            $this->assertVersionIsFree($release->platform, $release->channel, $release->version, ignore: $release);
        }
    }
}
