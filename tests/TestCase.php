<?php

namespace TecClub\ReleaseKit\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use TecClub\ReleaseKit\ReleaseKitServiceProvider;

class TestCase extends Orchestra
{
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
    }
}
