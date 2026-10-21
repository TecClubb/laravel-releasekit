<?php

namespace TecClub\ReleaseKit\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use TecClub\ReleaseKit\ReleaseKitServiceProvider;

class TestCase extends Orchestra
{
    /**
     * Config applied before the package boots, for tests that need to
     * observe boot-time behaviour (call refreshApplication() after setting).
     *
     * @var array<string, mixed>
     */
    public static array $bootConfig = [];

    /**
     * The analytics table is publish-only in real apps. Tests always migrate
     * it up front: creating tables mid-test implicitly commits on MySQL and
     * would break RefreshDatabase isolation.
     */
    public static bool $withAnalyticsTable = true;

    protected function tearDown(): void
    {
        static::$bootConfig = [];
        static::$withAnalyticsTable = true;

        parent::tearDown();
    }

    protected function getEnvironmentSetUp($app)
    {
        if (static::$withAnalyticsTable) {
            $app->afterResolving('migrator', fn ($migrator) => $migrator->path(__DIR__.'/../database/migrations/analytics'));
        }
    }

    protected function getPackageProviders($app)
    {
        return [
            ReleaseKitServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        // CI runs the suite against MySQL and PostgreSQL by setting DB_CONNECTION.
        $connection = env('DB_CONNECTION', 'testing');

        if ($connection === 'testing') {
            $app['config']->set('database.connections.testing.foreign_key_constraints', true);
        }

        $app['config']->set('database.default', $connection);

        foreach (static::$bootConfig as $key => $value) {
            $app['config']->set($key, $value);
        }
    }
}
