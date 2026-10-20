<?php

use Illuminate\Support\Facades\Storage;
use TecClub\ReleaseKit\Support\DiskResolver;

beforeEach(function () {
    $this->resolver = app(DiskResolver::class);
    config()->set('filesystems.default', 'local');
    config()->set('releasekit.storage.disk', null);
});

it('falls back to the system default disk', function () {
    expect($this->resolver->name())->toBe('local')
        ->and($this->resolver->name('stable'))->toBe('local');
});

it('prefers the configured storage disk over the system default', function () {
    config()->set('releasekit.storage.disk', 'public');

    expect($this->resolver->name('stable'))->toBe('public');
});

it('prefers the channel disk over everything else', function () {
    config()->set('releasekit.storage.disk', 'public');
    config()->set('releasekit.channels.beta.disk', 'private');

    expect($this->resolver->name('beta'))->toBe('private')
        ->and($this->resolver->name('stable'))->toBe('public')
        ->and($this->resolver->name())->toBe('public');
});

it('returns the filesystem for the resolved disk', function () {
    Storage::fake('builds');
    config()->set('releasekit.storage.disk', 'builds');

    $this->resolver->disk()->put('file.txt', 'x');

    Storage::disk('builds')->assertExists('file.txt');
});

it('normalises the storage directory', function () {
    config()->set('releasekit.storage.directory', '/builds/');

    expect($this->resolver->directory())->toBe('builds');
});
