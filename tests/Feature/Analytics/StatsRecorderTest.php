<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use TecClub\ReleaseKit\Enums\StatEvent;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Models\Stat;
use TecClub\ReleaseKit\Services\StatsRecorder;

beforeEach(function () {
    config()->set('releasekit.analytics.enabled', true);
});

function recorder(): StatsRecorder
{
    return app(StatsRecorder::class);
}

it('is disabled by default', function () {
    config()->set('releasekit.analytics.enabled', false);

    recorder()->recordCheck('android', 'stable', '1.0.0');

    expect(recorder()->enabled())->toBeFalse()
        ->and(Stat::count())->toBe(0);
});

it('counts checks into one daily row per dimension set', function () {
    recorder()->recordCheck('android', 'stable', '1.0.0', 'arm64-v8a', 'PK');
    recorder()->recordCheck('android', 'stable', '1.0.0', 'arm64-v8a', 'PK');
    recorder()->recordCheck('android', 'stable', '1.0.0', 'arm64-v8a', 'PK');

    $stat = Stat::sole();

    expect($stat->count)->toBe(3)
        ->and($stat->event)->toBe(StatEvent::Check)
        ->and($stat->date->toDateString())->toBe(now()->toDateString())
        ->and($stat->only(['platform', 'channel', 'version', 'architecture', 'artifact_id', 'country']))->toBe([
            'platform' => 'android', 'channel' => 'stable', 'version' => '1.0.0',
            'architecture' => 'arm64-v8a', 'artifact_id' => 0, 'country' => 'PK',
        ]);
});

it('stores missing dimensions as empty values so they still aggregate', function () {
    recorder()->recordCheck('ios', 'stable', '2.0');
    recorder()->recordCheck('ios', 'stable', '2.0');

    expect(Stat::sole()->only(['architecture', 'country', 'count']))->toBe(['architecture' => '', 'country' => '', 'count' => 2]);
});

it('starts a new row for each day, version and country', function () {
    recorder()->recordCheck('android', 'stable', '1.0.0', country: 'PK');
    recorder()->recordCheck('android', 'stable', '1.0.0', country: 'US');
    recorder()->recordCheck('android', 'stable', '1.1.0', country: 'PK');
    $this->travel(1)->days();
    recorder()->recordCheck('android', 'stable', '1.0.0', country: 'PK');

    expect(Stat::count())->toBe(4)->and((int) Stat::sum('count'))->toBe(4);
});

it('counts downloads by artifact and release version', function () {
    $artifact = Artifact::factory()
        ->for(Release::factory()->published()->state(['platform' => 'windows', 'channel' => 'beta', 'version' => '3.1.0']))
        ->architecture(null)
        ->create(['format' => 'exe']);

    recorder()->recordDownload($artifact, 'DE');
    recorder()->recordDownload($artifact, 'DE');

    expect(Stat::sole()->only(['event', 'platform', 'channel', 'version', 'architecture', 'artifact_id', 'country', 'count']))->toBe([
        'event' => StatEvent::Download, 'platform' => 'windows', 'channel' => 'beta', 'version' => '3.1.0',
        'architecture' => '', 'artifact_id' => $artifact->id, 'country' => 'DE', 'count' => 2,
    ]);
});

it('honours the per-event switches', function () {
    config()->set('releasekit.analytics.track_checks', false);
    config()->set('releasekit.analytics.track_downloads', false);

    recorder()->recordCheck('android', 'stable', '1.0');
    recorder()->recordDownload(Artifact::factory()->create());

    expect(Stat::count())->toBe(0);
});

it('does nothing when the stats table is missing', function () {
    Schema::drop('app_release_stats');

    recorder()->recordCheck('android', 'stable', '1.0');

    expect(recorder()->enabled())->toBeFalse();
})->after(fn () => restoreStatsTable());

it('never throws when a write fails', function () {
    Log::spy();
    config()->set('releasekit.tables.stats', 'app_release_stats');
    recorder()->enabled(); // table detected
    config()->set('releasekit.tables.stats', 'missing_stats_table');

    recorder()->recordCheck('android', 'stable', '1.0');

    Log::shouldHaveReceived('warning')->once();
});

it('reads the country from the configured header', function (?string $header, ?string $value, ?string $expected) {
    config()->set('releasekit.analytics.country_header', $header);
    $request = Request::create('/check');

    if ($value !== null) {
        $request->headers->set('CF-IPCountry', $value);
    }

    expect(recorder()->countryFrom($request))->toBe($expected);
})->with([
    'valid' => ['CF-IPCountry', 'pk', 'PK'],
    'tor marker' => ['CF-IPCountry', 'T1', 'T1'],
    'missing' => ['CF-IPCountry', null, null],
    'malformed' => ['CF-IPCountry', 'Pakistan', null],
    'disabled' => [null, 'PK', null],
]);
