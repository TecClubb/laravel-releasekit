<?php

use Illuminate\Support\Facades\DB;
use TecClub\ReleaseKit\Actions\UpdateTrackMinimum;
use TecClub\ReleaseKit\Actions\YankRelease;
use TecClub\ReleaseKit\Enums\CheckStatus;
use TecClub\ReleaseKit\Exceptions\InvalidVersion;
use TecClub\ReleaseKit\Exceptions\UnsupportedValue;
use TecClub\ReleaseKit\Facades\ReleaseKit;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Link;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Services\VersionChecker;

beforeEach(function () {
    registerDownloadRoute();
    config()->set('cache.default', 'array');
});

function check(string $version, string $platform = 'android', ?string $channel = null, ?string $architecture = null)
{
    return app(VersionChecker::class)->check($platform, $version, $channel, $architecture);
}

it('reports each status', function (string $client, CheckStatus $expected) {
    currentRelease('1.2.0');
    app(UpdateTrackMinimum::class)->handle('android', 'stable', '1.0.0');
    app(YankRelease::class)->handle(Release::factory()->published()->create(['version' => '1.1.5']));

    expect(check($client)->status)->toBe($expected);
})->with([
    'yanked' => ['1.1.5', CheckStatus::Blocked],
    'yanked, padded' => ['1.1.5.0', CheckStatus::Blocked],
    'below minimum' => ['0.9.0', CheckStatus::UpdateRequired],
    'below current' => ['1.1.0', CheckStatus::UpdateAvailable],
    'at minimum' => ['1.0', CheckStatus::UpdateAvailable],
    'equal' => ['1.2.0', CheckStatus::UpToDate],
    'equal, short' => ['1.2', CheckStatus::UpToDate],
    'newer dev build' => ['1.3.0', CheckStatus::UpToDate],
]);

it('blocks a yanked version even when it is above the minimum and current', function () {
    currentRelease('1.0.0');
    app(YankRelease::class)->handle(Release::factory()->published()->create(['version' => '2.0.0']));

    expect(check('2.0.0')->status)->toBe(CheckStatus::Blocked);
});

it('is up to date when there is no track', function () {
    $result = check('1.0.0');

    expect($result->status)->toBe(CheckStatus::UpToDate)
        ->and($result->current)->toBeNull()
        ->and($result->toArray()['current'])->toBeNull();
});

it('still enforces the minimum without a current release', function () {
    app(UpdateTrackMinimum::class)->handle('android', 'stable', '2.0');

    expect(check('1.9.9')->status)->toBe(CheckStatus::UpdateRequired)
        ->and(check('2.0')->status)->toBe(CheckStatus::UpToDate);
});

it('keeps channels isolated', function () {
    currentRelease('2.0.0', ['channel' => 'beta']);
    app(YankRelease::class)->handle(Release::factory()->published()->create(['version' => '1.0.0', 'channel' => 'beta']));

    expect(check('1.0.0')->status)->toBe(CheckStatus::UpToDate)
        ->and(check('1.0.0', channel: 'beta')->status)->toBe(CheckStatus::Blocked)
        ->and(check('1.5.0', channel: 'beta')->status)->toBe(CheckStatus::UpdateAvailable);
});

it('keeps platforms isolated', function () {
    currentRelease('2.0.0', ['platform' => 'ios']);

    expect(check('1.0.0')->status)->toBe(CheckStatus::UpToDate)
        ->and(check('1.0.0', platform: 'ios')->status)->toBe(CheckStatus::UpdateAvailable);
});

it('returns the response shape from the plan', function () {
    $release = currentRelease('1.2.0', ['build_number' => '45', 'release_notes' => 'Notes', 'published_at' => '2026-10-20 10:00:00']);
    $artifact = Artifact::factory()->for($release)->architecture('arm64-v8a')->create(['label' => 'APK (arm64)']);
    Link::factory()->create();
    app(UpdateTrackMinimum::class)->handle('android', 'stable', '1.0.0');

    $result = check('1.1.0', architecture: 'arm64-v8a');

    expect($result->toArray())->toBe([
        'status' => 'update_available',
        'client_version' => '1.1.0',
        'minimum_supported_version' => '1.0.0',
        'current' => [
            'version' => '1.2.0',
            'build_number' => '45',
            'release_notes' => 'Notes',
            'published_at' => '2026-10-20T10:00:00Z',
        ],
        'downloads' => [
            'artifacts' => [[
                'id' => $artifact->id,
                'architecture' => 'arm64-v8a',
                'format' => 'apk',
                'label' => 'APK (arm64)',
                'size_bytes' => $artifact->size_bytes,
                'checksum_sha256' => $artifact->checksum_sha256,
                'url' => route('releasekit.artifacts.download', $artifact->id),
            ]],
            'links' => [
                ['type' => 'store', 'store' => 'play_store', 'label' => 'Google Play', 'url' => 'https://play.google.com/store/apps/details?id=com.example.app'],
            ],
        ],
    ])->and($result->canUpdate())->toBeTrue()
        ->and($result->mustUpdate())->toBeFalse()
        ->and(json_encode($result))->toBe(json_encode($result->toArray()));
});

it('filters artifacts by architecture', function () {
    $release = currentRelease('1.0.0');
    Artifact::factory()->for($release)->architecture('arm64-v8a')->create();
    Artifact::factory()->for($release)->architecture('x86_64')->create();
    Artifact::factory()->for($release)->create();

    expect(check('0.9', architecture: 'x86_64')->downloads->artifacts)->toHaveCount(2)
        ->and(check('0.9')->downloads->artifacts)->toHaveCount(3);
});

it('validates platform, channel and version', function (array $input, string $exception) {
    expect(fn () => app(VersionChecker::class)->check(...$input))->toThrow($exception);
})->with([
    'platform' => [['symbian', '1.0'], UnsupportedValue::class],
    'channel' => [['android', '1.0', 'nightly'], UnsupportedValue::class],
    'version' => [['android', '1.0.0+45'], InvalidVersion::class],
]);

it('defaults to the default channel', function () {
    currentRelease('2.0', ['channel' => 'beta']);
    config()->set('releasekit.default_channel', 'beta');

    expect(check('1.0')->status)->toBe(CheckStatus::UpdateAvailable);
});

it('answers from the cache with zero queries once warm', function () {
    $release = currentRelease('1.2.0');
    Artifact::factory()->for($release)->create();
    check('1.0.0');

    DB::enableQueryLog();
    $result = check('1.1.0');

    expect(DB::getQueryLog())->toBe([])
        ->and($result->status)->toBe(CheckStatus::UpdateAvailable)
        ->and($result->downloads->artifacts)->toHaveCount(1);
});

it('sees changes immediately because writes clear the cache', function () {
    currentRelease('1.0.0');
    expect(check('1.0.0')->status)->toBe(CheckStatus::UpToDate);

    currentRelease('1.1.0');
    expect(check('1.0.0')->status)->toBe(CheckStatus::UpdateAvailable);

    app(UpdateTrackMinimum::class)->handle('android', 'stable', '1.1.0');
    expect(check('1.0.0')->status)->toBe(CheckStatus::UpdateRequired);
});

it('works the same through the facade', function () {
    currentRelease('2.0.0');

    expect(ReleaseKit::check(platform: 'android', version: '1.0.0', channel: 'stable')->status)->toBe(CheckStatus::UpdateAvailable);
});

it('ignores a track pointed at a non-published release by a direct write', function () {
    $release = currentRelease('2.0.0');
    $release->update(['status' => 'draft']);

    expect(check('1.0.0')->status)->toBe(CheckStatus::UpToDate)
        ->and(check('1.0.0')->current)->toBeNull();
});
