<?php

use TecClub\ReleaseKit\Enums\LinkType;
use TecClub\ReleaseKit\Enums\ReleaseStatus;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Link;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Models\Track;

it('casts release attributes', function () {
    $release = Release::factory()->published()->create(['metadata' => ['min_os' => '10']])->fresh();

    expect($release->status)->toBe(ReleaseStatus::Published)
        ->and($release->metadata)->toBe(['min_os' => '10'])
        ->and($release->published_at)->toBeInstanceOf(DateTimeInterface::class)
        ->and($release->isPublished())->toBeTrue()
        ->and($release->isDraft())->toBeFalse();
});

it('defaults new releases to draft', function () {
    $release = Release::create(['platform' => 'android', 'channel' => 'stable', 'version' => '1.0.0']);

    expect($release->status)->toBe(ReleaseStatus::Draft)
        ->and($release->fresh()->isDraft())->toBeTrue();
});

it('scopes releases by platform, channel and status', function () {
    Release::factory()->create(['platform' => 'android']);
    Release::factory()->published()->create(['platform' => 'android']);
    Release::factory()->yanked()->create(['platform' => 'android', 'channel' => 'beta']);
    Release::factory()->published()->create(['platform' => 'ios']);

    expect(Release::forPlatform('android')->count())->toBe(3)
        ->and(Release::forPlatform('android')->forChannel('stable')->count())->toBe(2)
        ->and(Release::published()->count())->toBe(2)
        ->and(Release::drafts()->count())->toBe(1)
        ->and(Release::yanked()->count())->toBe(1)
        ->and(Release::withStatus(ReleaseStatus::Yanked)->first()->isYanked())->toBeTrue();
});

it('relates releases, artifacts, links and tracks', function () {
    $release = Release::factory()->published()->create();
    $artifact = Artifact::factory()->for($release)->create();
    $link = Link::factory()->forRelease($release)->create();
    $track = Track::factory()->current($release)->create(['minimum_supported_version' => '1.0']);

    expect($release->artifacts->pluck('id')->all())->toBe([$artifact->id])
        ->and($release->links->pluck('id')->all())->toBe([$link->id])
        ->and($release->currentOnTrack->is($track))->toBeTrue()
        ->and($artifact->release->is($release))->toBeTrue()
        ->and($link->release->is($release))->toBeTrue()
        ->and($track->currentRelease->is($release))->toBeTrue();
});

it('filters artifacts by architecture including universal builds', function () {
    $release = Release::factory()->create();
    $arm = Artifact::factory()->for($release)->architecture('arm64-v8a')->create(['sort_order' => 2]);
    $x86 = Artifact::factory()->for($release)->architecture('x86_64')->create(['sort_order' => 1]);
    $universal = Artifact::factory()->for($release)->create(['sort_order' => 0]);
    Artifact::factory()->for($release)->architecture('arm64-v8a')->inactive()->create();

    expect(Artifact::active()->forArchitecture('arm64-v8a')->ordered()->pluck('id')->all())
        ->toBe([$universal->id, $arm->id])
        ->and(Artifact::active()->forArchitecture(null)->ordered()->pluck('id')->all())
        ->toBe([$universal->id, $x86->id, $arm->id])
        ->and($universal->isUniversal())->toBeTrue();
});

it('hides the storage location of artifacts', function () {
    $artifact = Artifact::factory()->create();

    expect($artifact->toArray())->not->toHaveKeys(['disk', 'path'])
        ->and($artifact->path)->toStartWith('releasekit/');
});

it('scopes links and resolves their labels', function () {
    $release = Release::factory()->create();
    $permanent = Link::factory()->create();
    $releaseLink = Link::factory()->forRelease($release)->external()->create(['label' => 'Direct download']);
    Link::factory()->inactive()->create();

    expect(Link::active()->permanent()->pluck('id')->all())->toBe([$permanent->id])
        ->and(Link::forPlatform('android')->count())->toBe(3)
        ->and($permanent->type)->toBe(LinkType::Store)
        ->and($permanent->isPermanent())->toBeTrue()
        ->and($permanent->displayLabel())->toBe('Google Play')
        ->and($releaseLink->displayLabel())->toBe('Direct download')
        ->and(Link::factory()->external()->make()->displayLabel())->toBeNull()
        ->and(Link::factory()->make(['store' => 'removed_store'])->displayLabel())->toBe('removed_store');
});

it('renders rows whose platform or channel was removed from config', function () {
    $release = Release::factory()->create(['platform' => 'symbian', 'channel' => 'nightly']);

    expect($release->fresh()->platformLabel())->toBe('symbian')
        ->and($release->channelLabel())->toBe('nightly');
});

it('resolves related models from config', function () {
    $custom = new class extends Artifact {};
    config()->set('releasekit.models.artifact', $custom::class);

    $release = Release::factory()->create();
    Artifact::factory()->for($release)->create();

    expect($release->artifacts()->first())->toBeInstanceOf($custom::class);
});
