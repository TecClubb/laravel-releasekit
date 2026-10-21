<?php

namespace TecClub\ReleaseKit;

use Illuminate\Support\Carbon;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use TecClub\ReleaseKit\Http\RouteRegistrar;
use TecClub\ReleaseKit\Observers\CacheInvalidationObserver;
use TecClub\ReleaseKit\Services\ReleaseCache;
use TecClub\ReleaseKit\Support\ChannelRegistry;
use TecClub\ReleaseKit\Support\DiskResolver;
use TecClub\ReleaseKit\Support\ModelResolver;
use TecClub\ReleaseKit\Support\PlatformRegistry;

class ReleaseKitServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('releasekit')
            ->hasConfigFile()
            ->hasMigrations([
                '2026_10_20_000001_create_app_releases_table',
                '2026_10_20_000002_create_app_release_tracks_table',
                '2026_10_20_000003_create_app_release_artifacts_table',
                '2026_10_20_000004_create_app_release_links_table',
            ])
            ->runsMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(PlatformRegistry::class);
        $this->app->singleton(ChannelRegistry::class);
        $this->app->singleton(DiskResolver::class);
        $this->app->singleton(ReleaseCache::class);
        $this->app->singleton(ReleaseKit::class);
    }

    public function bootingPackage(): void
    {
        // Hosts that publish the migrations call ReleaseKit::ignoreMigrations() in a service provider's register().
        $this->package->runsMigrations(ReleaseKit::$runsMigrations);
    }

    public function packageBooted(): void
    {
        // Observe the configured classes: host subclasses fire events under their own names.
        foreach ([ModelResolver::release(), ModelResolver::track(), ModelResolver::artifact(), ModelResolver::link()] as $model) {
            $model::observe(CacheInvalidationObserver::class);
        }

        // Routes are opt-in: hosts usually register them their own way (e.g. inside an API versioning setup).
        if (config('releasekit.routes.register', false) && ! $this->app->routesAreCached()) {
            $this->app->make(RouteRegistrar::class)->register();
        }

        if ($this->app->runningInConsole()) {
            $this->registerPublishables();
        }
    }

    private function registerPublishables(): void
    {
        // The stats table is optional: it is published on request and never loaded at runtime.
        $this->publishes([
            $this->package->basePath('/../database/migrations/analytics/2026_10_20_000005_create_app_release_stats_table.php') => $this->generateMigrationName('create_app_release_stats_table', Carbon::now()),
        ], 'releasekit-analytics-migrations');

        $this->publishes([
            $this->package->basePath('/../routes/releasekit.php') => base_path('routes/releasekit.php'),
        ], 'releasekit-routes');

        $controllers = [];

        foreach (glob($this->package->basePath('/../stubs/controllers/*.php.stub')) ?: [] as $stub) {
            $controllers[$stub] = app_path('Http/Controllers/ReleaseKit/'.basename($stub, '.stub'));
        }

        $this->publishes($controllers, 'releasekit-controllers');
    }
}
