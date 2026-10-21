<?php

namespace TecClub\ReleaseKit\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use TecClub\ReleaseKit\Http\Controllers\Concerns\AuthorizesChannels;
use TecClub\ReleaseKit\Http\Requests\ReleasesRequest;
use TecClub\ReleaseKit\Http\Resources\ReleaseResource;
use TecClub\ReleaseKit\ReleaseKit;

/**
 * GET {prefix}/releases?platform=android&channel=stable&page=1&per_page=20
 *
 * Published releases, newest first.
 */
class ReleasesController
{
    use AuthorizesChannels;

    public function __invoke(ReleasesRequest $request, ReleaseKit $releaseKit): AnonymousResourceCollection
    {
        $channel = $this->authorizeChannel($request, $request->channel());

        $releases = $releaseKit->releases($request->platform(), $channel)
            ->paginate($request->perPage())
            ->withQueryString();

        return ReleaseResource::collection($releases);
    }
}
