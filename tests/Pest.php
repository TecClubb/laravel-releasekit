<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use TecClub\ReleaseKit\Actions\PromoteRelease;
use TecClub\ReleaseKit\Http\RouteRegistrar;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);
uses(RefreshDatabase::class)->in('Feature');

/**
 * Registers a stand-in for the artifact download route the URL generator targets.
 */
function registerDownloadRoute(string $name = 'releasekit.artifacts.download'): void
{
    Route::get('releasekit/artifacts/{artifact}/download', fn () => 'download')->name($name);
    Route::getRoutes()->refreshNameLookups();
}

/**
 * Registers the package API the same way auto-registration does.
 *
 * @param  array<string, mixed>  $config  releasekit.* keys, e.g. ['routes.prefix' => 'api/v1']
 */
function withReleaseKitRoutes(array $config = []): void
{
    foreach ($config as $key => $value) {
        config()->set("releasekit.{$key}", $value);
    }

    app(RouteRegistrar::class)->register();
    Route::getRoutes()->refreshNameLookups();
}

/**
 * Re-creates the stats table after a test dropped it (no-op if it exists).
 */
function restoreStatsTable(): void
{
    if (! Schema::hasTable(config('releasekit.tables.stats', 'app_release_stats'))) {
        (include __DIR__.'/../database/migrations/analytics/2026_10_20_000005_create_app_release_stats_table.php')->up();
    }
}

/**
 * A published release that is current on its track.
 *
 * @param  array<string, mixed>  $attributes
 */
function currentRelease(string $version, array $attributes = []): Release
{
    $release = Release::factory()->published()->create(['version' => $version, ...$attributes]);
    app(PromoteRelease::class)->handle($release);

    return $release;
}
