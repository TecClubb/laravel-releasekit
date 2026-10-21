<?php

use Illuminate\Support\Facades\Route;
use TecClub\ReleaseKit\Http\Controllers\ArtifactDownloadController;
use TecClub\ReleaseKit\Http\Controllers\CheckController;
use TecClub\ReleaseKit\Http\Controllers\DownloadsController;
use TecClub\ReleaseKit\Http\Controllers\ReleasesController;

/*
|--------------------------------------------------------------------------
| ReleaseKit API routes
|--------------------------------------------------------------------------
|
| Published by: php artisan vendor:publish --tag=releasekit-routes
|
| Include this file however your app registers API routes, for example in
| bootstrap/app.php or routes/api.php:
|
|     Route::prefix('api/releasekit')
|         ->middleware(['api', 'throttle:120,1'])
|         ->group(base_path('routes/releasekit.php'));
|
| Remove any endpoint you don't need. Keep the route names: download URLs
| are generated from config('releasekit.routes.name').'artifacts.download'.
|
| Prefer pointing at the package controllers: they are thin, and copies
| (--tag=releasekit-controllers) stop receiving bug fixes.
|
*/

Route::name(config('releasekit.routes.name', 'releasekit.'))->group(function () {
    Route::get('check', CheckController::class)->name('check');
    Route::get('downloads', DownloadsController::class)->name('downloads');
    Route::get('releases', ReleasesController::class)->name('releases');
    Route::get('artifacts/{artifact}/download', ArtifactDownloadController::class)
        ->whereNumber('artifact')
        ->name('artifacts.download');
});
