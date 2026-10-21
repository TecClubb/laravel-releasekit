<?php

use App\Http\Controllers\ReleaseKit\CheckController;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use TecClub\ReleaseKit\ReleaseKitServiceProvider;
use TecClub\ReleaseKit\Tests\TestCase;

function releaseKitRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'releasekit.'))
        ->mapWithKeys(fn ($route) => [$route->getName() => $route])
        ->all();
}

it('registers no routes by default', function () {
    expect(releaseKitRoutes())->toBe([]);
});

it('auto-registers the enabled endpoints with the configured prefix and middleware', function () {
    TestCase::$bootConfig = [
        'releasekit.routes.register' => true,
        'releasekit.routes.prefix' => 'api/v1/updates',
        'releasekit.routes.middleware' => ['api'],
        'releasekit.routes.endpoints.releases' => false,
    ];
    $this->refreshApplication();

    $routes = releaseKitRoutes();

    expect(array_keys($routes))->toEqualCanonicalizing(['releasekit.check', 'releasekit.downloads', 'releasekit.artifacts.download'])
        ->and($routes['releasekit.check']->uri())->toBe('api/v1/updates/check')
        ->and($routes['releasekit.artifacts.download']->uri())->toBe('api/v1/updates/artifacts/{artifact}/download')
        ->and($routes['releasekit.check']->gatherMiddleware())->toBe(['api']);
});

it('honours a custom route name prefix', function () {
    withReleaseKitRoutes(['routes.name' => 'updates.']);

    expect(Route::has('updates.check'))->toBeTrue()
        ->and(Route::has('updates.artifacts.download'))->toBeTrue();
});

it('publishes the route stub and the controller copies', function () {
    $routes = ServiceProvider::pathsToPublish(ReleaseKitServiceProvider::class, 'releasekit-routes');
    $controllers = ServiceProvider::pathsToPublish(ReleaseKitServiceProvider::class, 'releasekit-controllers');

    expect(array_values($routes))->toBe([base_path('routes/releasekit.php')])
        ->and($controllers)->toHaveCount(4)
        ->and(array_map('basename', array_values($controllers)))->toEqualCanonicalizing([
            'CheckController.php', 'DownloadsController.php', 'ReleasesController.php', 'ArtifactDownloadController.php',
        ])
        ->and(array_values($controllers)[0])->toStartWith(app_path('Http/Controllers/ReleaseKit'));
});

it('keeps the controller stubs identical to the package controllers apart from the namespace', function () {
    foreach (glob(__DIR__.'/../../../stubs/controllers/*.php.stub') as $stub) {
        $source = __DIR__.'/../../../src/Http/Controllers/'.basename($stub, '.stub');

        expect(str_replace('namespace App\Http\Controllers\ReleaseKit;', 'namespace TecClub\ReleaseKit\Http\Controllers;', File::get($stub)))
            ->toBe(File::get($source));
    }
});

it('serves the API from the published route stub inside a host route group', function () {
    Route::prefix('api/v2/releasekit')->middleware('api')->group(__DIR__.'/../../../routes/releasekit.php');
    Route::getRoutes()->refreshNameLookups();
    currentRelease('2.0.0');

    $this->getJson('/api/v2/releasekit/check?platform=android&version=1.0.0')
        ->assertOk()
        ->assertJsonPath('status', 'update_available');

    expect(Route::getRoutes()->getByName('releasekit.artifacts.download')->uri())
        ->toBe('api/v2/releasekit/artifacts/{artifact}/download');
});

it('works with published controller copies', function () {
    $directory = sys_get_temp_dir().'/releasekit-controllers-'.uniqid();
    File::ensureDirectoryExists($directory);

    foreach (glob(__DIR__.'/../../../stubs/controllers/*.php.stub') as $stub) {
        $target = $directory.'/'.basename($stub, '.stub');
        File::copy($stub, $target);
        require_once $target;
    }

    Route::get('custom/check', CheckController::class);
    currentRelease('1.0.0');

    $this->getJson('/custom/check?platform=android&version=1.0.0')->assertOk()->assertJsonPath('status', 'up_to_date');

    File::deleteDirectory($directory);
});
