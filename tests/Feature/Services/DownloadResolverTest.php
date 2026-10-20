<?php

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use TecClub\ReleaseKit\Exceptions\MissingDownloadRoute;
use TecClub\ReleaseKit\Exceptions\UnsupportedValue;
use TecClub\ReleaseKit\Facades\ReleaseKit;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Link;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Services\DownloadResolver;

beforeEach(function () {
    registerDownloadRoute();
    config()->set('cache.default', 'array');
});

function downloads(?string $channel = null, ?string $platform = null, ?string $architecture = null)
{
    return app(DownloadResolver::class)->all($channel, $platform, $architecture);
}

it('returns exact architecture matches plus universal artifacts in sort order', function () {
    $release = currentRelease('1.0.0');
    $universal = Artifact::factory()->for($release)->create(['sort_order' => 2]);
    $arm = Artifact::factory()->for($release)->architecture('arm64-v8a')->create(['sort_order' => 1]);
    Artifact::factory()->for($release)->architecture('x86_64')->create(['sort_order' => 0]);

    $ids = fn (?string $architecture) => array_map(
        fn ($download) => $download->artifact->id,
        downloads(architecture: $architecture)->platform('android')->artifacts,
    );

    expect($ids('arm64-v8a'))->toBe([$arm->id, $universal->id])
        ->and($ids(null))->toHaveCount(3);
});

it('excludes inactive and non-downloadable artifacts', function () {
    $release = currentRelease('1.0.0');
    $apk = Artifact::factory()->for($release)->create();
    Artifact::factory()->for($release)->inactive()->create();
    Artifact::factory()->for($release)->create(['format' => 'aab']);

    expect(array_map(fn ($d) => $d->artifact->id, downloads()->platform('android')->artifacts))->toBe([$apk->id]);
});

it('applies downloadable_formats from config at read time', function () {
    $release = currentRelease('1.0.0');
    Artifact::factory()->for($release)->create(['format' => 'aab']);
    expect(downloads()->platform('android'))->toBeNull();

    config()->set('releasekit.platforms.android.downloadable_formats', ['apk', 'aab']);

    expect(downloads()->platform('android')->artifacts)->toHaveCount(1);
});

it('merges release links before permanent platform links', function () {
    $release = currentRelease('1.0.0');
    $platformLink = Link::factory()->create(['sort_order' => 0]);
    $releaseLink = Link::factory()->forRelease($release)->external()->create(['sort_order' => 5]);
    Link::factory()->inactive()->create();
    Link::factory()->create(['platform' => 'ios', 'store' => 'app_store']);

    $urls = array_map(fn ($link) => $link->url, downloads(platform: 'android')->platform('android')->links);

    expect($urls)->toBe([$releaseLink->url, $platformLink->url]);
});

it('only uses links of the current release', function () {
    currentRelease('2.0.0');
    $old = Release::factory()->published()->create(['version' => '1.0.0']);
    Link::factory()->forRelease($old)->external()->create();

    expect(downloads()->platform('android'))->toBeNull();
});

it('omits platforms with nothing to offer and lists the rest', function () {
    Artifact::factory()->for(currentRelease('1.0.0', ['platform' => 'windows']))->create(['format' => 'exe']);
    Link::factory()->create(['platform' => 'ios', 'store' => 'app_store', 'url' => 'https://apps.apple.com/app/id1']);
    currentRelease('1.0.0', ['platform' => 'linux']); // current but nothing attached

    $result = downloads();

    expect(array_keys($result->platforms))->toBe(['ios', 'windows'])
        ->and($result->platform('ios')->current)->toBeNull()
        ->and($result->platform('windows')->label)->toBe('Windows')
        ->and($result->channel)->toBe('stable');
});

it('returns the downloads response shape from the plan', function () {
    $release = currentRelease('1.1.3', ['platform' => 'windows', 'build_number' => '42', 'published_at' => '2026-10-01 08:30:00']);
    $artifact = Artifact::factory()->for($release)->architecture('x64')->create(['format' => 'exe']);
    Link::factory()->create(['platform' => 'windows', 'store' => 'microsoft_store', 'url' => 'https://apps.microsoft.com/x']);

    expect(ReleaseKit::downloads(platform: 'windows')->toArray())->toBe([
        'channel' => 'stable',
        'platforms' => [
            'windows' => [
                'label' => 'Windows',
                'current' => ['version' => '1.1.3', 'build_number' => '42', 'published_at' => '2026-10-01T08:30:00Z'],
                'artifacts' => [[
                    'id' => $artifact->id,
                    'architecture' => 'x64',
                    'format' => 'exe',
                    'label' => null,
                    'size_bytes' => $artifact->size_bytes,
                    'checksum_sha256' => $artifact->checksum_sha256,
                    'url' => route('releasekit.artifacts.download', $artifact->id),
                ]],
                'links' => [['type' => 'store', 'store' => 'microsoft_store', 'label' => 'Microsoft Store', 'url' => 'https://apps.microsoft.com/x']],
            ],
        ],
    ]);
});

it('validates platform and channel', function (array $input) {
    downloads(...$input);
})->with([
    'channel' => [['channel' => 'nightly']],
    'platform' => [['platform' => 'symbian']],
])->throws(UnsupportedValue::class);

it('picks up a platform added in config without a migration', function () {
    config()->set('releasekit.platforms.tvos', [
        'label' => 'tvOS', 'architectures' => [], 'formats' => ['ipa'], 'downloadable_formats' => [], 'stores' => ['app_store'],
    ]);
    Link::factory()->create(['platform' => 'tvos', 'store' => 'app_store']);

    expect(downloads()->platform('tvos')->label)->toBe('tvOS');
});

describe('download URLs', function () {
    it('uses temporary signed route URLs for protected channels', function () {
        config()->set('releasekit.delivery.mode', 'public');
        config()->set('releasekit.delivery.public_through_app', false);
        $artifact = Artifact::factory()->for(currentRelease('1.0.0', ['channel' => 'beta']))->create();

        $url = downloads('beta')->platform('android')->primaryArtifact()->url;

        expect($url)->toContain('signature=')->toContain('expires=')
            ->and(URL::hasValidSignature(request()->create($url)))->toBeTrue();

        $this->travel(301)->seconds();

        expect(URL::hasValidSignature(request()->create($url)))->toBeFalse();
    });

    it('generates signed URLs after the cache read, never caching them', function () {
        $artifact = Artifact::factory()->for(currentRelease('1.0.0', ['channel' => 'beta']))->create();

        $first = downloads('beta')->platform('android')->primaryArtifact()->url;
        $this->travel(10)->seconds();
        $second = downloads('beta')->platform('android')->primaryArtifact()->url;

        expect($second)->not->toBe($first)
            ->and(URL::hasValidSignature(request()->create($second)))->toBeTrue();
    });

    it('uses the plain app route for public channels', function (string $mode) {
        config()->set('releasekit.delivery.mode', $mode);
        $artifact = Artifact::factory()->for(currentRelease('1.0.0'))->create();

        expect(downloads()->platform('android')->primaryArtifact()->url)->toBe(route('releasekit.artifacts.download', $artifact->id));
    })->with(['stream', 'signed', 'public']);

    it('returns the storage URL directly in public mode without the app', function () {
        Storage::fake('cdn', ['url' => 'https://cdn.example.com']);
        config()->set('releasekit.delivery.mode', 'public');
        config()->set('releasekit.delivery.public_through_app', false);
        $artifact = Artifact::factory()->for(currentRelease('1.0.0'))->create(['disk' => 'cdn', 'path' => 'releasekit/a.apk']);

        expect(downloads()->platform('android')->primaryArtifact()->url)->toBe('https://cdn.example.com/releasekit/a.apk');
    });

    it('goes through the app when the disk has no public URL', function () {
        Storage::fake('private');
        config()->set('releasekit.delivery.mode', 'public');
        config()->set('releasekit.delivery.public_through_app', false);
        $artifact = Artifact::factory()->for(currentRelease('1.0.0'))->create(['disk' => 'private']);

        expect(downloads()->platform('android')->primaryArtifact()->url)->toBe(route('releasekit.artifacts.download', $artifact->id));
    });

    it('explains a missing download route', function () {
        Artifact::factory()->for(currentRelease('1.0.0'))->create();
        config()->set('releasekit.routes.name', 'renamed.');

        downloads();
    })->throws(MissingDownloadRoute::class, 'renamed.artifacts.download');

    it('follows a custom route name prefix', function () {
        registerDownloadRoute('api.v1.releasekit.artifacts.download');
        config()->set('releasekit.routes.name', 'api.v1.releasekit.');
        $artifact = Artifact::factory()->for(currentRelease('1.0.0'))->create();

        expect(downloads()->platform('android')->primaryArtifact()->url)
            ->toBe(route('api.v1.releasekit.artifacts.download', $artifact->id));
    });
});

describe('isDownloadable', function () {
    it('requires an active, downloadable artifact on a published release', function () {
        $resolver = app(DownloadResolver::class);
        $published = Release::factory()->published()->create();

        expect($resolver->isDownloadable(Artifact::factory()->for($published)->create()))->toBeTrue()
            ->and($resolver->isDownloadable(Artifact::factory()->for($published)->inactive()->create()))->toBeFalse()
            ->and($resolver->isDownloadable(Artifact::factory()->for($published)->create(['format' => 'aab'])))->toBeFalse()
            ->and($resolver->isDownloadable(Artifact::factory()->for(Release::factory())->create()))->toBeFalse()
            ->and($resolver->isDownloadable(Artifact::factory()->for(Release::factory()->yanked())->create()))->toBeFalse();
    });
});

describe('releases()', function () {
    it('lists published releases newest first and paginates', function () {
        Release::factory()->published()->create(['version' => '1.0.0', 'published_at' => now()->subDays(3)]);
        Release::factory()->published()->create(['version' => '1.1.0', 'published_at' => now()->subDays(1)]);
        Release::factory()->create(['version' => '1.2.0']);
        Release::factory()->yanked()->create(['version' => '1.0.5']);
        Release::factory()->published()->create(['version' => '9.0.0', 'channel' => 'beta']);
        Release::factory()->published()->create(['version' => '3.0.0', 'platform' => 'ios']);

        expect(ReleaseKit::releases(platform: 'android', channel: 'stable')->pluck('version')->all())->toBe(['1.1.0', '1.0.0'])
            ->and(ReleaseKit::releases()->count())->toBe(3)
            ->and(ReleaseKit::releases(platform: 'android')->paginate(1)->total())->toBe(2);
    });
});
