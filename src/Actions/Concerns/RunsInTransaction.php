<?php

namespace TecClub\ReleaseKit\Actions\Concerns;

use Closure;
use TecClub\ReleaseKit\Support\ModelResolver;

trait RunsInTransaction
{
    /**
     * Runs the callback in a transaction on the releases connection.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    protected function transaction(Closure $callback): mixed
    {
        $release = ModelResolver::release();

        return (new $release)->getConnection()->transaction($callback);
    }
}
