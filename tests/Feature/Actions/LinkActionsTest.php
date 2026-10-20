<?php

use TecClub\ReleaseKit\Actions\AddLink;
use TecClub\ReleaseKit\Actions\DeleteLink;
use TecClub\ReleaseKit\Actions\UpdateLink;
use TecClub\ReleaseKit\Enums\LinkType;
use TecClub\ReleaseKit\Exceptions\InvalidLink;
use TecClub\ReleaseKit\Exceptions\ReleaseLocked;
use TecClub\ReleaseKit\Exceptions\UnsupportedValue;
use TecClub\ReleaseKit\Models\Link;
use TecClub\ReleaseKit\Models\Release;

describe('AddLink', function () {
    it('adds a permanent store link', function () {
        $link = app(AddLink::class)->handle(platform: 'android', store: 'play_store', url: 'https://play.google.com/store/apps/details?id=x');

        expect($link->type)->toBe(LinkType::Store)
            ->and($link->isPermanent())->toBeTrue()
            ->and($link->displayLabel())->toBe('Google Play');
    });

    it('adds an external release link that takes the release platform', function () {
        $release = Release::factory()->create(['platform' => 'windows']);

        $link = app(AddLink::class)->handle(release: $release, url: 'https://example.com/setup.exe', label: 'Mirror', sortOrder: 2);

        expect($link->type)->toBe(LinkType::External)
            ->and($link->platform)->toBe('windows')
            ->and($link->app_release_id)->toBe($release->id)
            ->and($link->sort_order)->toBe(2);
    });

    it('accepts app store URL schemes', function (string $url) {
        expect(app(AddLink::class)->handle(platform: 'android', url: $url)->url)->toBe($url);
    })->with(['market://details?id=com.example', 'itms-apps://apps.apple.com/app/id123']);

    it('rejects invalid and unsafe URLs', function (string $url) {
        app(AddLink::class)->handle(platform: 'android', url: $url);
    })->with([
        'not a url' => 'play store',
        'javascript' => 'javascript://example.com/%0Aalert(1)',
        'data' => 'data://text/html,hi',
        'too long' => 'https://example.com/'.str_repeat('a', 2048),
    ])->throws(InvalidLink::class);

    it('requires a platform for permanent links', function () {
        app(AddLink::class)->handle(url: 'https://example.com');
    })->throws(InvalidLink::class, 'needs a platform');

    it('rejects a platform that differs from the release', function () {
        app(AddLink::class)->handle(platform: 'ios', release: Release::factory()->create(['platform' => 'android']), url: 'https://example.com');
    })->throws(InvalidLink::class, 'does not match');

    it('validates platform and store against config', function (array $input) {
        app(AddLink::class)->handle(...['url' => 'https://example.com', ...$input]);
    })->with([
        'unknown platform' => [['platform' => 'symbian']],
        'unknown store' => [['platform' => 'android', 'store' => 'f_droid']],
        'store of another platform' => [['platform' => 'android', 'store' => 'app_store']],
    ])->throws(UnsupportedValue::class);

    it('is locked on yanked releases', function () {
        app(AddLink::class)->handle(release: Release::factory()->yanked()->create(), url: 'https://example.com');
    })->throws(ReleaseLocked::class);
});

describe('UpdateLink', function () {
    it('updates a link and re-derives its type from the store', function () {
        $link = Link::factory()->create();

        app(UpdateLink::class)->handle($link, ['store' => null, 'url' => 'https://example.com/apk', 'label' => 'Direct', 'is_active' => false, 'sort_order' => 1]);

        expect($link->fresh()->type)->toBe(LinkType::External)
            ->and($link->fresh()->is_active)->toBeFalse();

        app(UpdateLink::class)->handle($link, ['store' => 'play_store']);

        expect($link->fresh()->type)->toBe(LinkType::Store);
    });

    it('validates store and url', function (array $attributes, string $exception) {
        expect(fn () => app(UpdateLink::class)->handle(Link::factory()->create(), $attributes))->toThrow($exception);
    })->with([
        'store' => [['store' => 'app_store'], UnsupportedValue::class],
        'url' => [['url' => 'nope'], InvalidLink::class],
        'platform is fixed' => [['platform' => 'ios'], InvalidArgumentException::class],
    ]);

    it('is locked on yanked releases', function () {
        app(UpdateLink::class)->handle(Link::factory()->forRelease(Release::factory()->yanked()->create())->create(), ['label' => 'x']);
    })->throws(ReleaseLocked::class);
});

describe('DeleteLink', function () {
    it('deletes permanent and release links', function () {
        app(DeleteLink::class)->handle(Link::factory()->create());
        app(DeleteLink::class)->handle(Link::factory()->forRelease(Release::factory()->published()->create())->create());

        expect(Link::count())->toBe(0);
    });

    it('is locked on yanked releases', function () {
        app(DeleteLink::class)->handle(Link::factory()->forRelease(Release::factory()->yanked()->create())->create());
    })->throws(ReleaseLocked::class);
});
