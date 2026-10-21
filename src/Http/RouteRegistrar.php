<?php

namespace TecClub\ReleaseKit\Http;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\Router;
use TecClub\ReleaseKit\Http\Controllers\ArtifactDownloadController;
use TecClub\ReleaseKit\Http\Controllers\CheckController;
use TecClub\ReleaseKit\Http\Controllers\DownloadsController;
use TecClub\ReleaseKit\Http\Controllers\ReleasesController;

/**
 * Registers the API when releasekit.routes.register is true, using the
 * configured prefix, name prefix and middleware, and only the enabled
 * endpoints. Hosts that register routes themselves publish
 * routes/releasekit.php instead.
 */
class RouteRegistrar
{
    public function __construct(
        private readonly Router $router,
        private readonly Repository $config,
    ) {}

    public function register(): void
    {
        $this->router->group([
            'prefix' => (string) $this->config->get('releasekit.routes.prefix', 'api/releasekit'),
            'as' => (string) $this->config->get('releasekit.routes.name', 'releasekit.'),
            'middleware' => (array) $this->config->get('releasekit.routes.middleware', ['api']),
        ], function (Router $router) {
            if ($this->enabled('check')) {
                $router->get('check', CheckController::class)->name('check');
            }

            if ($this->enabled('downloads')) {
                $router->get('downloads', DownloadsController::class)->name('downloads');
            }

            if ($this->enabled('releases')) {
                $router->get('releases', ReleasesController::class)->name('releases');
            }

            if ($this->enabled('artifact_download')) {
                $router->get('artifacts/{artifact}/download', ArtifactDownloadController::class)
                    ->whereNumber('artifact')
                    ->name('artifacts.download');
            }
        });
    }

    private function enabled(string $endpoint): bool
    {
        return (bool) $this->config->get("releasekit.routes.endpoints.{$endpoint}", true);
    }
}
