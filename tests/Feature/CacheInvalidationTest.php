<?php

use Illuminate\Support\Facades\Cache;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Link;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Models\Track;
use TecClub\ReleaseKit\Services\ReleaseCache;

beforeEach(function () {
    config()->set('cache.default', 'array');
    $this->cache = app(ReleaseCache::class);
});

function warm(string $platform = 'android', string $channel = 'stable'): void
{
    app(ReleaseCache::class)->remember($platform, $channel, fn () => 'cached');
}

function isWarm(string $platform = 'android', string $channel = 'stable'): bool
{
    return Cache::has(app(ReleaseCache::class)->key($platform, $channel));
}

it('caches per platform and channel', function () {
    $calls = 0;
    $this->cache->remember('android', 'stable', function () use (&$calls) {
        return ++$calls;
    });
    $value = $this->cache->remember('android', 'stable', fn () => 99);

    expect($value)->toBe(1)->and(isWarm())->toBeTrue()->and(isWarm('android', 'beta'))->toBeFalse();
});

it('bypasses the cache when disabled', function () {
    config()->set('releasekit.cache.enabled', false);

    $this->cache->remember('android', 'stable', fn () => 1);

    expect(isWarm())->toBeFalse()
        ->and($this->cache->remember('android', 'stable', fn () => 2))->toBe(2);
});

it('clears on release writes, including direct model writes', function () {
    $release = Release::factory()->create();
    warm();
    warm('ios');

    $release->update(['release_notes' => 'x']);

    expect(isWarm())->toBeFalse()->and(isWarm('ios'))->toBeTrue();

    warm();
    $release->delete();

    expect(isWarm())->toBeFalse();
});

it('clears both the old and the new platform and channel', function () {
    $release = Release::factory()->create(['platform' => 'android', 'channel' => 'stable']);
    warm();
    warm('ios', 'beta');

    $release->update(['platform' => 'ios', 'channel' => 'beta']);

    expect(isWarm())->toBeFalse()->and(isWarm('ios', 'beta'))->toBeFalse();
});

it('clears on track writes', function () {
    $track = Track::factory()->create();
    warm();

    $track->update(['minimum_supported_version' => '1.0']);

    expect(isWarm())->toBeFalse();
});

it('clears the release platform and channel on artifact writes', function () {
    $release = Release::factory()->create(['channel' => 'beta']);
    $artifact = Artifact::factory()->for($release)->create();
    warm('android', 'beta');
    warm();

    $artifact->update(['label' => 'x']);

    expect(isWarm('android', 'beta'))->toBeFalse()->and(isWarm())->toBeTrue();

    warm('android', 'beta');
    $artifact->delete();

    expect(isWarm('android', 'beta'))->toBeFalse();
});

it('clears every channel of the platform on link writes', function () {
    $link = Link::factory()->create(['platform' => 'android']);
    warm();
    warm('android', 'beta');
    warm('ios');

    $link->update(['label' => 'Play']);

    expect(isWarm())->toBeFalse()
        ->and(isWarm('android', 'beta'))->toBeFalse()
        ->and(isWarm('ios'))->toBeTrue();
});

it('uses the configured cache store', function () {
    config()->set('cache.stores.releasekit', ['driver' => 'array']);
    config()->set('releasekit.cache.store', 'releasekit');

    warm();

    expect(Cache::store('releasekit')->has($this->cache->key('android', 'stable')))->toBeTrue()
        ->and(Cache::store('array')->has($this->cache->key('android', 'stable')))->toBeFalse();
});
