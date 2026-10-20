<?php

use Illuminate\Support\Facades\Event;
use TecClub\ReleaseKit\Actions\PromoteRelease;
use TecClub\ReleaseKit\Actions\UpdateTrackMinimum;
use TecClub\ReleaseKit\Actions\YankRelease;
use TecClub\ReleaseKit\Enums\ReleaseStatus;
use TecClub\ReleaseKit\Events\ReleasePromoted;
use TecClub\ReleaseKit\Events\ReleaseYanked;
use TecClub\ReleaseKit\Events\TrackMinimumUpdated;
use TecClub\ReleaseKit\Exceptions\InvalidPromotion;
use TecClub\ReleaseKit\Exceptions\InvalidStatusTransition;
use TecClub\ReleaseKit\Exceptions\InvalidVersion;
use TecClub\ReleaseKit\Exceptions\ReleaseIsCurrent;
use TecClub\ReleaseKit\Exceptions\UnsupportedValue;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Link;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Models\Track;

function published(string $version, array $attributes = []): Release
{
    return Release::factory()->published()->create(['version' => $version, ...$attributes]);
}

describe('PromoteRelease', function () {
    it('makes a published release current and reports the previous one', function () {
        Event::fake([ReleasePromoted::class]);
        $old = published('1.0.0');
        $new = published('1.1.0');
        app(PromoteRelease::class)->handle($old);

        $track = app(PromoteRelease::class)->handle($new);

        expect($track->current_release_id)->toBe($new->id)
            ->and($new->currentOnTrack->is($track))->toBeTrue();

        Event::assertDispatched(ReleasePromoted::class, fn ($e) => $e->release->is($new) && $e->previous?->is($old));
    });

    it('creates the track when it does not exist', function () {
        app(PromoteRelease::class)->handle(published('1.0.0', ['platform' => 'linux']));

        expect(Track::forPlatform('linux')->forChannel('stable')->value('current_release_id'))->not->toBeNull();
    });

    it('rolls back by promoting an older release', function () {
        $old = published('1.0.0');
        app(PromoteRelease::class)->handle(published('1.1.0'));

        expect(app(PromoteRelease::class)->handle($old)->current_release_id)->toBe($old->id);
    });

    it('rejects drafts and yanked releases', function (string $state) {
        $release = $state === 'draft' ? Release::factory()->create() : Release::factory()->yanked()->create();

        app(PromoteRelease::class)->handle($release);
    })->with(['draft', 'yanked'])->throws(InvalidPromotion::class);

    it('rejects a track of another platform or channel', function (array $track) {
        app(PromoteRelease::class)->handle(published('1.0.0'), Track::factory()->create($track));
    })->with([
        'platform' => [['platform' => 'ios', 'channel' => 'stable']],
        'channel' => [['platform' => 'android', 'channel' => 'beta']],
    ])->throws(InvalidPromotion::class);

    it('rejects a release below the minimum supported version', function () {
        Track::factory()->create(['minimum_supported_version' => '1.1']);

        app(PromoteRelease::class)->handle(published('1.0.9'));
    })->throws(InvalidPromotion::class, 'minimum');

    it('is a no-op when the release is already current', function () {
        $release = published('1.0.0');
        app(PromoteRelease::class)->handle($release);
        Event::fake([ReleasePromoted::class]);

        app(PromoteRelease::class)->handle($release);

        Event::assertNotDispatched(ReleasePromoted::class);
    });
});

describe('PromoteRelease::warnings', function () {
    it('reports architectures and formats the current release has', function () {
        $current = published('1.0.0');
        Artifact::factory()->for($current)->architecture('arm64-v8a')->create();
        Artifact::factory()->for($current)->architecture('x86_64')->create();
        Artifact::factory()->for($current)->create(['format' => 'aab']);
        app(PromoteRelease::class)->handle($current);

        $next = published('1.1.0');
        Artifact::factory()->for($next)->architecture('arm64-v8a')->create();

        $warnings = app(PromoteRelease::class)->warnings($next);

        expect($warnings)->toHaveCount(2)
            ->and($warnings[0])->toContain('apk build for x86_64')
            ->and($warnings[1])->toContain('aab build for universal');
    });

    it('treats a universal build as covering every architecture', function () {
        $current = published('1.0.0');
        Artifact::factory()->for($current)->architecture('arm64-v8a')->create();
        app(PromoteRelease::class)->handle($current);

        $next = published('1.1.0');
        Artifact::factory()->for($next)->create();

        expect(app(PromoteRelease::class)->warnings($next))->toBe([]);
    });

    it('ignores inactive artifacts on the current release', function () {
        $current = published('1.0.0');
        Artifact::factory()->for($current)->architecture('x86_64')->inactive()->create();
        Artifact::factory()->for($current)->create();
        app(PromoteRelease::class)->handle($current);

        $next = published('1.1.0');
        Artifact::factory()->for($next)->create();

        expect(app(PromoteRelease::class)->warnings($next))->toBe([]);
    });

    it('warns about drafts, rollbacks, the minimum and empty releases without throwing', function () {
        app(PromoteRelease::class)->handle(published('2.0.0'));
        app(UpdateTrackMinimum::class)->handle('android', 'stable', '1.5');

        $warnings = app(PromoteRelease::class)->warnings(Release::factory()->create(['version' => '1.0.0']));

        expect(implode("\n", $warnings))
            ->toContain('publish it before')
            ->toContain('below the minimum supported version 1.5')
            ->toContain('rollback')
            ->toContain('nothing to download');
    });

    it('counts permanent platform links as something to offer', function () {
        Link::factory()->create(['platform' => 'ios', 'store' => 'app_store']);

        expect(app(PromoteRelease::class)->warnings(published('1.0.0', ['platform' => 'ios'])))->toBe([]);
    });

    it('notes when the release is already current', function () {
        $release = published('1.0.0');
        app(PromoteRelease::class)->handle($release);

        expect(app(PromoteRelease::class)->warnings($release))->toHaveCount(1)
            ->and(app(PromoteRelease::class)->warnings($release)[0])->toContain('already the current release');
    });
});

describe('YankRelease', function () {
    it('yanks a published release that is not current', function () {
        Event::fake([ReleaseYanked::class]);
        $release = published('1.0.0');

        app(YankRelease::class)->handle($release, reason: 'Crash on launch');

        expect($release->status)->toBe(ReleaseStatus::Yanked)
            ->and($release->yanked_at)->not->toBeNull()
            ->and($release->yank_reason)->toBe('Crash on launch');

        Event::assertDispatched(ReleaseYanked::class, fn ($e) => $e->rollbackTo === null);
    });

    it('requires a rollback target to yank the current release', function () {
        $release = published('1.0.0');
        app(PromoteRelease::class)->handle($release);

        expect(fn () => app(YankRelease::class)->handle($release))->toThrow(ReleaseIsCurrent::class)
            ->and($release->fresh()->isPublished())->toBeTrue();
    });

    it('moves the track and yanks in one step', function () {
        Event::fake([ReleaseYanked::class, ReleasePromoted::class]);
        $previous = published('1.0.0');
        $broken = published('1.1.0');
        app(PromoteRelease::class)->handle($broken);

        app(YankRelease::class)->handle($broken, reason: 'Crash', rollbackTo: $previous);

        expect($broken->fresh()->isYanked())->toBeTrue()
            ->and(Track::sole()->current_release_id)->toBe($previous->id);

        Event::assertDispatched(ReleaseYanked::class, fn ($e) => $e->rollbackTo?->is($previous));
        Event::assertDispatched(ReleasePromoted::class, fn ($e) => $e->release->is($previous) && $e->previous?->is($broken));
    });

    it('keeps everything unchanged when the rollback target is invalid', function (string $case) {
        $broken = published('1.1.0');
        app(PromoteRelease::class)->handle($broken);

        $target = match ($case) {
            'draft' => Release::factory()->create(['version' => '1.0.0']),
            'other platform' => published('1.0.0', ['platform' => 'ios']),
            'other channel' => published('1.0.0', ['channel' => 'beta']),
            'itself' => $broken,
        };

        expect(fn () => app(YankRelease::class)->handle($broken, rollbackTo: $target))->toThrow(InvalidPromotion::class)
            ->and($broken->fresh()->isPublished())->toBeTrue()
            ->and(Track::sole()->current_release_id)->toBe($broken->id);
    })->with(['draft', 'other platform', 'other channel', 'itself']);

    it('ignores the rollback target when the release is not current', function () {
        $current = published('1.0.0');
        app(PromoteRelease::class)->handle($current);
        $other = published('0.9.0');

        app(YankRelease::class)->handle(published('1.1.0'), rollbackTo: $other);

        expect(Track::sole()->current_release_id)->toBe($current->id);
    });

    it('only yanks published releases', function (string $state) {
        $release = $state === 'draft' ? Release::factory()->create() : Release::factory()->yanked()->create();

        app(YankRelease::class)->handle($release);
    })->with(['draft', 'yanked'])->throws(InvalidStatusTransition::class);

    it('truncates long reasons', function () {
        $release = app(YankRelease::class)->handle(published('1.0.0'), reason: str_repeat('x', 300));

        expect(mb_strlen($release->fresh()->yank_reason))->toBe(255);
    });
});

describe('UpdateTrackMinimum', function () {
    it('sets, changes and clears the minimum', function () {
        Event::fake([TrackMinimumUpdated::class]);
        app(PromoteRelease::class)->handle(published('2.0.0'));

        $track = app(UpdateTrackMinimum::class)->handle('android', 'stable', '1.5');
        expect($track->minimum_supported_version)->toBe('1.5');

        app(UpdateTrackMinimum::class)->handle('android', 'stable', '2.0.0');
        expect($track->fresh()->minimum_supported_version)->toBe('2.0.0');

        app(UpdateTrackMinimum::class)->handle('android', 'stable', null);
        expect($track->fresh()->minimum_supported_version)->toBeNull();

        Event::assertDispatchedTimes(TrackMinimumUpdated::class, 3);
        Event::assertDispatched(TrackMinimumUpdated::class, fn ($e) => $e->previousMinimum === '1.5');
    });

    it('rejects a minimum above the current release', function () {
        app(PromoteRelease::class)->handle(published('1.2.0'));

        app(UpdateTrackMinimum::class)->handle('android', 'stable', '1.2.1');
    })->throws(InvalidVersion::class, 'cannot be higher');

    it('allows any minimum before a release is current and creates the track', function () {
        $track = app(UpdateTrackMinimum::class)->handle('macos', 'beta', '3.0');

        expect($track->exists)->toBeTrue()
            ->and($track->minimum_supported_version)->toBe('3.0')
            ->and($track->current_release_id)->toBeNull();
    });

    it('validates its input', function (array $input, string $exception) {
        expect(fn () => app(UpdateTrackMinimum::class)->handle(...$input))->toThrow($exception);
    })->with([
        'platform' => [['symbian', 'stable', '1.0'], UnsupportedValue::class],
        'channel' => [['android', 'nightly', '1.0'], UnsupportedValue::class],
        'version' => [['android', 'stable', '1'], InvalidVersion::class],
    ]);

    it('does not fire an event when nothing changes', function () {
        app(UpdateTrackMinimum::class)->handle('android', 'stable', '1.0');
        Event::fake([TrackMinimumUpdated::class]);

        app(UpdateTrackMinimum::class)->handle('android', 'stable', '1.0');

        Event::assertNotDispatched(TrackMinimumUpdated::class);
    });
});
