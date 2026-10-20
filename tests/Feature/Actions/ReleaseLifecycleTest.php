<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use TecClub\ReleaseKit\Actions\DeleteDraftRelease;
use TecClub\ReleaseKit\Actions\PublishRelease;
use TecClub\ReleaseKit\Actions\UnyankRelease;
use TecClub\ReleaseKit\Enums\ReleaseStatus;
use TecClub\ReleaseKit\Events\ReleasePublished;
use TecClub\ReleaseKit\Events\ReleaseUnyanked;
use TecClub\ReleaseKit\Exceptions\InvalidStatusTransition;
use TecClub\ReleaseKit\Exceptions\ReleaseLocked;
use TecClub\ReleaseKit\Jobs\DeleteArtifactFile;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Link;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Models\Track;

describe('PublishRelease', function () {
    it('publishes a draft and creates its track without making it current', function () {
        Event::fake([ReleasePublished::class]);
        $release = Release::factory()->create(['platform' => 'windows', 'channel' => 'beta']);

        app(PublishRelease::class)->handle($release);

        $track = Track::forPlatform('windows')->forChannel('beta')->sole();

        expect($release->status)->toBe(ReleaseStatus::Published)
            ->and($release->published_at)->not->toBeNull()
            ->and($track->current_release_id)->toBeNull();

        Event::assertDispatched(ReleasePublished::class);
    });

    it('reuses an existing track', function () {
        $track = Track::factory()->create(['platform' => 'android', 'channel' => 'stable']);

        app(PublishRelease::class)->handle(Release::factory()->create());

        expect(Track::count())->toBe(1)->and(Track::first()->is($track))->toBeTrue();
    });

    it('only publishes drafts', function (string $state) {
        app(PublishRelease::class)->handle(Release::factory()->{$state}()->create());
    })->with(['published', 'yanked'])->throws(InvalidStatusTransition::class);

    it('checks the stored status, not a stale model', function () {
        $release = Release::factory()->create();
        $stale = Release::find($release->id);

        app(PublishRelease::class)->handle($release);
        app(PublishRelease::class)->handle($stale);
    })->throws(InvalidStatusTransition::class);
});

describe('UnyankRelease', function () {
    it('publishes a yanked release again and clears the yank details', function () {
        Event::fake([ReleaseUnyanked::class]);
        $release = Release::factory()->yanked('Crash')->create();
        $publishedAt = $release->published_at;

        app(UnyankRelease::class)->handle($release);

        expect($release->status)->toBe(ReleaseStatus::Published)
            ->and($release->yanked_at)->toBeNull()
            ->and($release->yank_reason)->toBeNull()
            ->and($release->published_at->equalTo($publishedAt))->toBeTrue();

        Event::assertDispatched(ReleaseUnyanked::class);
    });

    it('only unyanks yanked releases', function (string $state) {
        $factory = Release::factory();
        app(UnyankRelease::class)->handle(($state === 'draft' ? $factory : $factory->published())->create());
    })->with(['draft', 'published'])->throws(InvalidStatusTransition::class);
});

describe('DeleteDraftRelease', function () {
    it('deletes a draft with its artifacts and links and queues file deletion', function () {
        Bus::fake([DeleteArtifactFile::class]);

        $release = Release::factory()->create();
        $artifacts = Artifact::factory()->for($release)->count(2)->create();
        Link::factory()->forRelease($release)->create();
        $permanent = Link::factory()->create();

        app(DeleteDraftRelease::class)->handle($release);

        expect(Release::count())->toBe(0)
            ->and(Artifact::count())->toBe(0)
            ->and(Link::pluck('id')->all())->toBe([$permanent->id]);

        foreach ($artifacts as $artifact) {
            Bus::assertDispatched(DeleteArtifactFile::class, fn ($job) => $job->path === $artifact->path && $job->disk === $artifact->disk);
        }
    });

    it('never deletes published or yanked releases', function (string $state) {
        $release = Release::factory()->{$state}()->create();

        expect(fn () => app(DeleteDraftRelease::class)->handle($release))->toThrow(ReleaseLocked::class)
            ->and($release->fresh())->not->toBeNull();
    })->with(['published', 'yanked']);

    it('is backed by the FK when a current release is deleted directly', function () {
        $release = Release::factory()->published()->create();
        Track::factory()->current($release)->create();

        $release->delete();
    })->throws(QueryException::class);
});
