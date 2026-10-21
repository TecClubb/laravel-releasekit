<?php

use TecClub\ReleaseKit\Enums\StatEvent;
use TecClub\ReleaseKit\Models\Stat;

beforeEach(function () {
    $rows = [
        ['2026-10-01', 'check', 'android', 'stable', '1.0.0', 'PK', 0, 10],
        ['2026-10-01', 'check', 'android', 'stable', '1.1.0', 'US', 0, 4],
        ['2026-10-02', 'check', 'android', 'stable', '1.1.0', 'PK', 0, 6],
        ['2026-10-02', 'check', 'android', 'beta', '1.2.0', 'PK', 0, 3],
        ['2026-10-03', 'check', 'ios', 'stable', '1.0.0', 'US', 0, 7],
        ['2026-10-02', 'download', 'android', 'stable', '1.1.0', 'PK', 42, 5],
    ];

    foreach ($rows as [$date, $event, $platform, $channel, $version, $country, $artifact, $count]) {
        Stat::query()->insert(compact('date', 'event', 'platform', 'channel', 'version', 'country', 'count') + ['artifact_id' => $artifact, 'architecture' => '']);
    }
});

it('filters by event, platform, channel, artifact and date range', function () {
    expect((int) Stat::event(StatEvent::Check)->sum('count'))->toBe(30)
        ->and((int) Stat::event('download')->sum('count'))->toBe(5)
        ->and((int) Stat::forPlatform('ios')->sum('count'))->toBe(7)
        ->and((int) Stat::forChannel('beta')->sum('count'))->toBe(3)
        ->and((int) Stat::forArtifact(42)->sum('count'))->toBe(5)
        ->and((int) Stat::between('2026-10-02', '2026-10-03')->sum('count'))->toBe(21)
        ->and((int) Stat::between(new DateTime('2026-10-01'), new DateTime('2026-10-01'))->sum('count'))->toBe(14);
});

it('groups totals by version, highest first', function () {
    $totals = Stat::event(StatEvent::Check)->forPlatform('android')->forChannel('stable')->groupByVersion()->get();

    expect($totals->map(fn ($row) => [$row->version, (int) $row->total])->all())->toBe([
        ['1.0.0', 10],
        ['1.1.0', 10],
    ]);
});

it('groups totals by date, country and platform', function () {
    expect(Stat::event(StatEvent::Check)->groupByDate()->get()->map(fn ($row) => [$row->date->toDateString(), (int) $row->total])->all())
        ->toBe([['2026-10-01', 14], ['2026-10-02', 9], ['2026-10-03', 7]])
        ->and(Stat::event(StatEvent::Check)->groupByCountry()->get()->map(fn ($row) => [$row->country, (int) $row->total])->all())
        ->toBe([['PK', 19], ['US', 11]])
        ->and(Stat::query()->groupByPlatform()->get()->map(fn ($row) => [$row->platform, (int) $row->total])->all())
        ->toBe([['android', 28], ['ios', 7]]);
});
