<?php

use TecClub\ReleaseKit\Enums\ChannelAccess;
use TecClub\ReleaseKit\Support\ChannelRegistry;

beforeEach(function () {
    $this->channels = app(ChannelRegistry::class);
});

it('lists the configured channels and the default', function () {
    expect($this->channels->keys())->toBe(['stable', 'beta'])
        ->and($this->channels->has('beta'))->toBeTrue()
        ->and($this->channels->has('nightly'))->toBeFalse()
        ->and($this->channels->default())->toBe('stable');
});

it('reads channel access', function () {
    expect($this->channels->access('stable'))->toBe(ChannelAccess::Public)
        ->and($this->channels->isPublic('stable'))->toBeTrue()
        ->and($this->channels->access('beta'))->toBe(ChannelAccess::Protected)
        ->and($this->channels->isProtected('beta'))->toBeTrue();
});

it('fails closed for unknown channels and invalid access values', function () {
    config()->set('releasekit.channels.alpha', ['access' => 'open']);

    expect($this->channels->access('nightly'))->toBe(ChannelAccess::Protected)
        ->and($this->channels->access('alpha'))->toBe(ChannelAccess::Protected);
});

it('falls back to the raw key as label', function () {
    config()->set('releasekit.channels.beta.label', 'Beta testers');

    expect($this->channels->label('beta'))->toBe('Beta testers')
        ->and($this->channels->label('stable'))->toBe('stable');
});

it('returns the channel disk override', function () {
    config()->set('releasekit.channels.beta.disk', 'private');

    expect($this->channels->disk('beta'))->toBe('private')
        ->and($this->channels->disk('stable'))->toBeNull();
});
