<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Link;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Models\Track;
use TecClub\ReleaseKit\ReleaseKit;
use TecClub\ReleaseKit\ReleaseKitServiceProvider;
use TecClub\ReleaseKit\Tests\TestCase;

it('creates the four core tables', function () {
    expect(Schema::hasColumns('app_releases', [
        'id', 'platform', 'channel', 'version', 'build_number', 'status', 'release_notes',
        'metadata', 'published_at', 'yanked_at', 'yank_reason', 'created_at', 'updated_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('app_release_tracks', [
            'id', 'platform', 'channel', 'current_release_id', 'minimum_supported_version',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('app_release_artifacts', [
            'id', 'app_release_id', 'architecture', 'format', 'label', 'disk', 'path', 'original_filename',
            'mime_type', 'size_bytes', 'checksum_sha256', 'is_active', 'sort_order', 'metadata',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('app_release_links', [
            'id', 'platform', 'app_release_id', 'type', 'store', 'label', 'url', 'is_active', 'sort_order',
        ]))->toBeTrue();
});

it('never loads the analytics migration at runtime', function () {
    TestCase::$withAnalyticsTable = false;
    $this->refreshApplication();

    expect(collect(app('migrator')->paths())->filter(fn (string $path) => str_contains($path, 'analytics')))->toBeEmpty();
});

it('publishes the analytics migration under its own tag', function () {
    $paths = ServiceProvider::pathsToPublish(ReleaseKitServiceProvider::class, 'releasekit-analytics-migrations');

    expect($paths)->toHaveCount(1)
        ->and(array_key_first($paths))->toEndWith('create_app_release_stats_table.php')
        ->and(array_values($paths)[0])->toMatch('/\d{4}_\d{2}_\d{2}_\d{6}_create_app_release_stats_table\.php$/');
});

it('publishes the core migrations and config', function () {
    expect(ServiceProvider::pathsToPublish(ReleaseKitServiceProvider::class, 'releasekit-migrations'))->toHaveCount(4)
        ->and(ServiceProvider::pathsToPublish(ReleaseKitServiceProvider::class, 'releasekit-config'))->toHaveCount(1);
});

it('creates and drops the analytics table from the published migration', function () {
    // The test harness has already migrated it (see TestCase::$withAnalyticsTable).
    $migration = include __DIR__.'/../../database/migrations/analytics/2026_10_20_000005_create_app_release_stats_table.php';

    $migration->down();
    expect(Schema::hasTable('app_release_stats'))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumns('app_release_stats', [
        'date', 'event', 'platform', 'channel', 'version', 'architecture', 'artifact_id', 'country', 'count',
    ]))->toBeTrue();
});

it('enforces one release per platform, channel and version', function () {
    Release::factory()->create(['platform' => 'android', 'channel' => 'stable', 'version' => '1.0.0']);
    Release::factory()->create(['platform' => 'android', 'channel' => 'beta', 'version' => '1.0.0']);
    Release::factory()->create(['platform' => 'ios', 'channel' => 'stable', 'version' => '1.0.0']);

    Release::factory()->create(['platform' => 'android', 'channel' => 'stable', 'version' => '1.0.0']);
})->throws(QueryException::class);

it('enforces one track per platform and channel', function () {
    Track::factory()->create(['platform' => 'android', 'channel' => 'stable']);
    Track::factory()->create(['platform' => 'android', 'channel' => 'stable']);
})->throws(QueryException::class);

it('restricts deleting a release that is current on a track', function () {
    $release = Release::factory()->published()->create();
    Track::factory()->current($release)->create();

    $release->delete();
})->throws(QueryException::class);

it('cascades release deletes to artifacts and release links but keeps platform links', function () {
    $release = Release::factory()->create();
    Artifact::factory()->for($release)->count(2)->create();
    Link::factory()->forRelease($release)->create();
    $permanent = Link::factory()->create(['platform' => $release->platform]);

    $release->delete();

    expect(Artifact::count())->toBe(0)
        ->and(Link::pluck('id')->all())->toBe([$permanent->id]);
});

it('honours custom table names from config', function () {
    config()->set('releasekit.tables', [
        'releases' => 'rk_releases',
        'tracks' => 'rk_tracks',
        'artifacts' => 'rk_artifacts',
        'links' => 'rk_links',
        'stats' => 'rk_stats',
    ]);

    $migrations = __DIR__.'/../../database/migrations';

    foreach ([...glob("{$migrations}/*.php"), ...glob("{$migrations}/analytics/*.php")] as $file) {
        (include $file)->up();
    }

    expect(Schema::hasTable('rk_releases'))->toBeTrue()
        ->and(Schema::hasTable('rk_tracks'))->toBeTrue()
        ->and(Schema::hasTable('rk_artifacts'))->toBeTrue()
        ->and(Schema::hasTable('rk_links'))->toBeTrue()
        ->and(Schema::hasTable('rk_stats'))->toBeTrue();

    $release = Release::factory()->create();

    expect($release->getTable())->toBe('rk_releases')
        ->and(Artifact::factory()->for($release)->create()->getTable())->toBe('rk_artifacts');
});

it('lets hosts opt out of the package migrations', function () {
    $loadsPackageMigrations = fn () => collect(app('migrator')->paths())
        ->contains(fn (string $path) => str_ends_with($path, 'create_app_releases_table.php'));

    expect($loadsPackageMigrations())->toBeTrue();

    ReleaseKit::ignoreMigrations();
    $this->refreshApplication();

    expect($loadsPackageMigrations())->toBeFalse();
})->after(fn () => ReleaseKit::$runsMigrations = true);
