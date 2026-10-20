<?php

use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Link;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Models\Stat;
use TecClub\ReleaseKit\Models\Track;

return [

    /*
    |--------------------------------------------------------------------------
    | Channels
    |--------------------------------------------------------------------------
    |
    | "access" is "public" or "protected". Protected channels require the
    | Gate ability configured under "access.ability".
    |
    | "disk" optionally overrides storage.disk for this channel
    | (e.g. keep beta builds in a private bucket).
    |
    | "label" is optional; the key is used when it is missing.
    |
    */

    'channels' => [
        'stable' => ['access' => 'public'],
        'beta' => ['access' => 'protected'],
    ],

    'default_channel' => 'stable',

    /*
    |--------------------------------------------------------------------------
    | Platforms
    |--------------------------------------------------------------------------
    |
    | Adding a platform, architecture, format or store is a config change,
    | never a migration. Only formats listed in "downloadable_formats" are
    | ever offered through the API.
    |
    */

    'platforms' => [
        'android' => [
            'label' => 'Android',
            'architectures' => ['arm64-v8a', 'armeabi-v7a', 'x86_64'],
            'formats' => ['apk', 'aab'],
            'downloadable_formats' => ['apk'], // aab is stored for archive, never offered
            'stores' => ['play_store'],
        ],
        'ios' => [
            'label' => 'iOS',
            'architectures' => [],
            'formats' => ['ipa'],
            'downloadable_formats' => [],
            'stores' => ['app_store', 'testflight'],
        ],
        'macos' => [
            'label' => 'macOS',
            'architectures' => ['arm64', 'x64'],
            'formats' => ['dmg', 'pkg', 'zip'],
            'downloadable_formats' => ['dmg', 'pkg', 'zip'],
            'stores' => ['mac_app_store'],
        ],
        'windows' => [
            'label' => 'Windows',
            'architectures' => ['x64', 'arm64', 'x86'],
            'formats' => ['exe', 'msi', 'msix', 'zip'],
            'downloadable_formats' => ['exe', 'msi', 'msix', 'zip'],
            'stores' => ['microsoft_store'],
        ],
        'linux' => [
            'label' => 'Linux',
            'architectures' => ['x64', 'arm64'],
            'formats' => ['appimage', 'deb', 'rpm', 'tar.gz'],
            'downloadable_formats' => ['appimage', 'deb', 'rpm', 'tar.gz'],
            'stores' => ['snap', 'flathub'],
        ],
    ],

    'stores' => [
        'play_store' => ['label' => 'Google Play'],
        'app_store' => ['label' => 'App Store'],
        'testflight' => ['label' => 'TestFlight'],
        'mac_app_store' => ['label' => 'Mac App Store'],
        'microsoft_store' => ['label' => 'Microsoft Store'],
        'snap' => ['label' => 'Snap Store'],
        'flathub' => ['label' => 'Flathub'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Disk resolution order: the channel's "disk", then "storage.disk",
    | then config('filesystems.default').
    |
    */

    'storage' => [
        'disk' => env('RELEASEKIT_DISK'), // null = config('filesystems.default')
        'directory' => env('RELEASEKIT_DIRECTORY', 'releasekit'),
        'max_upload_size_mb' => 2048,
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery
    |--------------------------------------------------------------------------
    |
    | "stream" | "signed" | "public". Protected channels never use "public".
    |
    */

    'delivery' => [
        'mode' => env('RELEASEKIT_DELIVERY', 'signed'),
        'signed_url_ttl' => 300, // seconds, for storage temporary URLs and signed routes

        // Only for "public" mode.
        // true  = the API returns the app's download URL, which 302-redirects to the public storage URL
        //         (downloads are counted, and links stay stable if the storage/CDN changes)
        // false = the API returns the public storage URL directly (no download analytics)
        'public_through_app' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Routes are not registered by default. Either set "register" to true,
    | or publish the stub with: php artisan vendor:publish --tag=releasekit-routes
    |
    */

    'routes' => [
        'register' => false,
        'prefix' => 'api/releasekit',
        'name' => 'releasekit.',
        'middleware' => ['api', 'throttle:120,1'],
        'endpoints' => [
            'check' => true,
            'downloads' => true,
            'releases' => true, // history / changelog
            'artifact_download' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Access
    |--------------------------------------------------------------------------
    |
    | Gate ability checked for protected channels:
    | fn (?Authenticatable $user, string $channel): bool
    |
    */

    'access' => [
        'ability' => 'releasekit.access-channel',
        'guard' => null, // null = default guard. e.g. 'sanctum'
    ],

    'cache' => [
        'enabled' => true,
        'store' => null, // null = default store
        'ttl' => 300, // seconds. Cleared automatically on changes
    ],

    'tables' => [
        'releases' => 'app_releases',
        'tracks' => 'app_release_tracks',
        'artifacts' => 'app_release_artifacts',
        'links' => 'app_release_links',
        'stats' => 'app_release_stats',
    ],

    'models' => [
        'release' => Release::class,
        'track' => Track::class,
        'artifact' => Artifact::class,
        'link' => Link::class,
        'stat' => Stat::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    |
    | Disabled by default. Requires the published migration:
    | php artisan vendor:publish --tag=releasekit-analytics-migrations
    |
    */

    'analytics' => [
        'enabled' => false,
        'track_checks' => true,
        'track_downloads' => true,
        'country_header' => 'CF-IPCountry', // null to disable country
    ],
];
