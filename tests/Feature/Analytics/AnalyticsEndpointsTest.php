<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use TecClub\ReleaseKit\Enums\StatEvent;
use TecClub\ReleaseKit\Facades\ReleaseKit;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Stat;

beforeEach(function () {
    config()->set('cache.default', 'array');
    config()->set('filesystems.default', 'local');
    config()->set('releasekit.analytics.enabled', true);
    Storage::fake('local');
    Storage::fake('cdn', ['url' => 'https://cdn.example.com']);
    withReleaseKitRoutes();
});

it('records a check with the client version, architecture and country', function () {
    currentRelease('2.0.0');

    $this->getJson('/api/releasekit/check?platform=android&version=1.5.0&architecture=arm64-v8a', ['CF-IPCountry' => 'PK'])->assertOk();
    $this->getJson('/api/releasekit/check?platform=android&version=1.5.0&architecture=arm64-v8a', ['CF-IPCountry' => 'PK'])->assertOk();

    expect(Stat::event(StatEvent::Check)->sole()->only(['version', 'architecture', 'country', 'count']))
        ->toBe(['version' => '1.5.0', 'architecture' => 'arm64-v8a', 'country' => 'PK', 'count' => 2]);
});

it('does not record rejected checks', function () {
    $this->getJson('/api/releasekit/check?platform=android&version=nope')->assertStatus(422);
    $this->getJson('/api/releasekit/check?platform=android&version=1.0&channel=beta')->assertForbidden();

    expect(Stat::count())->toBe(0);
});

it('does not count facade calls or the downloads listing', function () {
    Artifact::factory()->for(currentRelease('1.0.0'))->create();

    ReleaseKit::check('android', '0.9');
    $this->getJson('/api/releasekit/downloads')->assertOk();

    expect(Stat::count())->toBe(0);
});

it('records a download through the app, including public mode through the app', function (string $mode, string $disk) {
    config()->set('releasekit.delivery.mode', $mode);
    Storage::disk($disk)->put('releasekit/app.apk', 'x');
    $artifact = Artifact::factory()->for(currentRelease('1.0.0'))->create(['disk' => $disk, 'path' => 'releasekit/app.apk']);

    $this->get(route('releasekit.artifacts.download', $artifact->id), ['CF-IPCountry' => 'US']);

    expect(Stat::event(StatEvent::Download)->forArtifact($artifact)->value('count'))->toBe(1);
})->with([
    'stream' => ['stream', 'local'],
    'signed' => ['signed', 'local'],
    'public through app' => ['public', 'cdn'],
]);

it('cannot count downloads served straight from storage', function () {
    config()->set('releasekit.delivery.mode', 'public');
    config()->set('releasekit.delivery.public_through_app', false);
    Artifact::factory()->for(currentRelease('1.0.0'))->create(['disk' => 'cdn', 'path' => 'releasekit/app.apk']);

    $url = $this->getJson('/api/releasekit/downloads')->json('platforms.android.artifacts.0.url');

    expect($url)->toBe('https://cdn.example.com/releasekit/app.apk')
        ->and(Stat::count())->toBe(0);
});

it('keeps every endpoint working with analytics enabled but no table', function () {
    if (DB::connection()->getDriverName() === 'mysql') {
        $this->markTestSkipped('DROP TABLE commits implicitly on MySQL, so later writes would leak out of the test transaction.');
    }

    Schema::drop('app_release_stats');
    Storage::disk('local')->put('releasekit/app.apk', 'x');
    config()->set('releasekit.delivery.mode', 'stream');
    $artifact = Artifact::factory()->for(currentRelease('1.0.0'))->create(['disk' => 'local', 'path' => 'releasekit/app.apk']);

    $this->getJson('/api/releasekit/check?platform=android&version=0.9')->assertOk();
    $this->getJson('/api/releasekit/downloads')->assertOk();
    $this->getJson('/api/releasekit/releases')->assertOk();
    $this->get(route('releasekit.artifacts.download', $artifact->id))->assertOk();
})->after(function () {
    (include __DIR__.'/../../../database/migrations/analytics/2026_10_20_000005_create_app_release_stats_table.php')->up();
});
