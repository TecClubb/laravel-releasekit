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

    protected function tearDown(): void
    {
        static::$bootConfig = [];

        parent::tearDown();
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
