<?php

use Illuminate\Support\Facades\Event;
use TecClub\ReleaseKit\Actions\CreateRelease;
use TecClub\ReleaseKit\Actions\UpdateRelease;
use TecClub\ReleaseKit\Enums\ReleaseStatus;
use TecClub\ReleaseKit\Events\ReleaseCreated;
use TecClub\ReleaseKit\Exceptions\DuplicateRelease;
use TecClub\ReleaseKit\Exceptions\InvalidVersion;
use TecClub\ReleaseKit\Exceptions\ReleaseLocked;
use TecClub\ReleaseKit\Exceptions\UnsupportedValue;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Link;
use TecClub\ReleaseKit\Models\Release;

describe('CreateRelease', function () {
    it('creates a draft on the default channel', function () {
        Event::fake([ReleaseCreated::class]);

        $release = app(CreateRelease::class)->handle(
            platform: 'android',
            version: '1.2.0',
            buildNumber: '45',
            releaseNotes: 'Fixes',
            metadata: ['min_sdk' => 24],
        );

        expect($release->status)->toBe(ReleaseStatus::Draft)
            ->and($release->channel)->toBe('stable')
            ->and($release->fresh()->only(['platform', 'version', 'build_number', 'release_notes', 'metadata']))->toBe([
                'platform' => 'android',
                'version' => '1.2.0',
                'build_number' => '45',
                'release_notes' => 'Fixes',
                'metadata' => ['min_sdk' => 24],
            ]);

        Event::assertDispatched(ReleaseCreated::class, fn ($event) => $event->release->is($release));
    });

    it('rejects unknown platforms and channels', function (array $input) {
        app(CreateRelease::class)->handle(...$input);
    })->with([
        'platform' => [['platform' => 'symbian', 'version' => '1.0']],
        'channel' => [['platform' => 'android', 'version' => '1.0', 'channel' => 'nightly']],
    ])->throws(UnsupportedValue::class);

    it('rejects invalid versions', function () {
        app(CreateRelease::class)->handle(platform: 'android', version: '1.0.0-beta');
    })->throws(InvalidVersion::class);

    it('rejects an equal version on the same platform and channel', function (string $existing, string $new) {
        Release::factory()->create(['platform' => 'android', 'channel' => 'stable', 'version' => $existing]);

        app(CreateRelease::class)->handle(platform: 'android', version: $new);
    })->with([
        'identical' => ['1.2.0', '1.2.0'],
        'padded' => ['1.2', '1.2.0'],
        'leading zero' => ['1.2.0', '1.02.0'],
    ])->throws(DuplicateRelease::class);

    it('allows the same version on other platforms and channels', function () {
        Release::factory()->create(['platform' => 'android', 'channel' => 'stable', 'version' => '1.2.0']);

        app(CreateRelease::class)->handle(platform: 'ios', version: '1.2.0');
        app(CreateRelease::class)->handle(platform: 'android', version: '1.2.0', channel: 'beta');

        expect(Release::count())->toBe(3);
    });
});

describe('UpdateRelease', function () {
    it('updates every field of a draft', function () {
        $release = Release::factory()->create(['platform' => 'android', 'version' => '1.0.0']);

        app(UpdateRelease::class)->handle($release, [
            'platform' => 'ios',
            'channel' => 'beta',
            'version' => '1.0.1',
            'build_number' => '7',
            'release_notes' => 'New',
            'metadata' => ['a' => 1],
        ]);

        expect($release->fresh()->only(['platform', 'channel', 'version', 'build_number', 'release_notes']))->toBe([
            'platform' => 'ios',
            'channel' => 'beta',
            'version' => '1.0.1',
            'build_number' => '7',
            'release_notes' => 'New',
        ]);
    });

    it('only allows notes and metadata on published releases', function () {
        $release = Release::factory()->published()->create(['version' => '1.0.0']);

        app(UpdateRelease::class)->handle($release, ['release_notes' => 'Updated', 'metadata' => ['x' => true]]);

        expect($release->fresh()->release_notes)->toBe('Updated');

        app(UpdateRelease::class)->handle($release, ['version' => '2.0.0']);
    })->throws(ReleaseLocked::class, 'version');

    it('only allows notes on yanked releases', function () {
        $release = Release::factory()->yanked()->create();

        app(UpdateRelease::class)->handle($release, ['release_notes' => 'Crashes on launch']);
        expect($release->fresh()->release_notes)->toBe('Crashes on launch');

        app(UpdateRelease::class)->handle($release, ['metadata' => ['x' => 1]]);
    })->throws(ReleaseLocked::class);

    it('ignores locked fields that did not change', function () {
        $release = Release::factory()->published()->create(['platform' => 'android', 'channel' => 'stable', 'version' => '1.0.0']);

        app(UpdateRelease::class)->handle($release, [
            'platform' => 'android',
            'channel' => 'stable',
            'version' => '1.0.0',
            'release_notes' => 'Same form, new notes',
        ]);

        expect($release->fresh()->release_notes)->toBe('Same form, new notes');
    });

    it('leaves the model untouched when a rule fails', function () {
        $release = Release::factory()->published()->create(['version' => '1.0.0', 'release_notes' => 'Old']);

        try {
            app(UpdateRelease::class)->handle($release, ['version' => '2.0.0', 'release_notes' => 'New']);
        } catch (ReleaseLocked) {
        }

        expect($release->version)->toBe('1.0.0')
            ->and($release->release_notes)->toBe('Old')
            ->and($release->isDirty())->toBeFalse();
    });

    it('validates changed values on drafts', function (array $attributes, string $exception) {
        $release = Release::factory()->create();

        expect(fn () => app(UpdateRelease::class)->handle($release, $attributes))->toThrow($exception);
    })->with([
        'platform' => [['platform' => 'symbian'], UnsupportedValue::class],
        'channel' => [['channel' => 'nightly'], UnsupportedValue::class],
        'version' => [['version' => 'v2'], InvalidVersion::class],
    ]);

    it('rejects a version that collides with another release', function () {
        Release::factory()->create(['version' => '2.0.0']);
        $release = Release::factory()->create(['version' => '1.0.0']);

        app(UpdateRelease::class)->handle($release, ['version' => '2.0']);
    })->throws(DuplicateRelease::class);

    it('blocks platform changes while the draft has artifacts or links', function (string $child) {
        $release = Release::factory()->create();
        $child === 'artifact'
            ? Artifact::factory()->for($release)->create()
            : Link::factory()->forRelease($release)->create();

        app(UpdateRelease::class)->handle($release, ['platform' => 'ios']);
    })->with(['artifact', 'link'])->throws(ReleaseLocked::class);

    it('blocks channel changes while the draft has artifacts', function () {
        $release = Release::factory()->create();
        Artifact::factory()->for($release)->create();

        app(UpdateRelease::class)->handle($release, ['channel' => 'beta']);
    })->throws(ReleaseLocked::class);

    it('rejects unknown attributes', function () {
        app(UpdateRelease::class)->handle(Release::factory()->create(), ['status' => 'published']);
    })->throws(InvalidArgumentException::class);
});
