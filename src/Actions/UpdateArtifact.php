<?php

namespace TecClub\ReleaseKit\Actions;

use TecClub\ReleaseKit\Actions\Concerns\ValidatesInput;
use TecClub\ReleaseKit\Models\Artifact;

/**
 * Updates an artifact's descriptive fields. The file, its format and its
 * storage location are fixed; attach a new artifact to replace a build.
 */
class UpdateArtifact
{
    use ValidatesInput;

    public const EDITABLE = ['architecture', 'label', 'is_active', 'sort_order', 'metadata'];

    /**
     * @param  array<string, mixed>  $attributes  Keys: architecture, label, is_active, sort_order, metadata
     */
    public function handle(Artifact $artifact, array $attributes): Artifact
    {
        $this->assertKnownAttributes($attributes, self::EDITABLE);

        $release = $artifact->release;
        $this->assertContentsEditable($release);

        if (array_key_exists('architecture', $attributes)) {
            $this->assertArchitecture($release->platform, $attributes['architecture']);
        }

        $artifact->fill($attributes)->save();

        return $artifact;
    }
}
