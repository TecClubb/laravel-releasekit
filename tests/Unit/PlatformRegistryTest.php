<?php

use TecClub\ReleaseKit\Facades\ReleaseKit;
use TecClub\ReleaseKit\Support\PlatformRegistry;

beforeEach(function () {
    $this->platforms = app(PlatformRegistry::class);
});

it('lists the configured platforms', function () {
    expect($this->platforms->keys())->toBe(['android', 'ios', 'macos', 'windows', 'linux'])
        ->and($this->platforms->has('android'))->toBeTrue()
        ->and($this->platforms->has('tvos'))->toBeFalse();
});

it('returns labels, falling back to the raw key for unknown platforms', function () {
    expect($this->platforms->label('ios'))->toBe('iOS')
        ->and($this->platforms->label('tvos'))->toBe('tvos');
});

it('validates architectures and formats per platform', function () {
    expect($this->platforms->supportsArchitecture('android', 'arm64-v8a'))->toBeTrue()
        ->and($this->platforms->supportsArchitecture('android', 'x64'))->toBeFalse()
        ->and($this->platforms->supportsFormat('android', 'aab'))->toBeTrue()
        ->and($this->platforms->supportsFormat('android', 'exe'))->toBeFalse()
        ->and($this->platforms->architectures('ios'))->toBe([])
        ->and($this->platforms->formats('unknown'))->toBe([]);
});

it('only offers downloadable formats', function () {
    expect($this->platforms->isDownloadableFormat('android', 'apk'))->toBeTrue()
        ->and($this->platforms->isDownloadableFormat('android', 'aab'))->toBeFalse()
        ->and($this->platforms->downloadableFormats('ios'))->toBe([]);
});

it('never treats a format outside "formats" as downloadable', function () {
    config()->set('releasekit.platforms.android.downloadable_formats', ['apk', 'exe']);

    expect($this->platforms->downloadableFormats('android'))->toBe(['apk']);
});

it('validates stores per platform', function () {
    expect($this->platforms->supportsStore('android', 'play_store'))->toBeTrue()
        ->and($this->platforms->supportsStore('android', 'app_store'))->toBeFalse()
        ->and($this->platforms->storeLabel('play_store'))->toBe('Google Play')
        ->and($this->platforms->storeLabel('gone_store'))->toBe('gone_store');
});

it('requires a platform store to also exist in the stores list', function () {
    config()->set('releasekit.platforms.android.stores', ['play_store', 'f_droid']);

    expect($this->platforms->supportsStore('android', 'f_droid'))->toBeFalse();
});

it('picks up a new platform from config alone', function () {
    config()->set('releasekit.platforms.tvos', [
        'label' => 'tvOS',
        'architectures' => [],
        'formats' => ['ipa'],
        'downloadable_formats' => [],
        'stores' => ['app_store'],
    ]);

    expect($this->platforms->has('tvos'))->toBeTrue()
        ->and($this->platforms->label('tvos'))->toBe('tvOS')
        ->and($this->platforms->supportsStore('tvos', 'app_store'))->toBeTrue();
});

it('is reachable through the facade', function () {
    expect(ReleaseKit::platforms())->toBeInstanceOf(PlatformRegistry::class);
});
