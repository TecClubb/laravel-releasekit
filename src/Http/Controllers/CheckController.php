<?php

namespace TecClub\ReleaseKit\Http\Controllers;

use TecClub\ReleaseKit\Http\Controllers\Concerns\AuthorizesChannels;
use TecClub\ReleaseKit\Http\Requests\CheckRequest;
use TecClub\ReleaseKit\Http\Resources\CheckResultResource;
use TecClub\ReleaseKit\Services\StatsRecorder;
use TecClub\ReleaseKit\Services\VersionChecker;

/**
 * GET {prefix}/check?platform=android&version=1.1.0&channel=stable&architecture=arm64-v8a
 */
class CheckController
{
    use AuthorizesChannels;

    public function __invoke(CheckRequest $request, VersionChecker $checker, StatsRecorder $stats): CheckResultResource
    {
        $channel = $this->authorizeChannel($request, $request->channel());

        $result = $checker->check(
            platform: (string) $request->platform(),
            version: $request->version(),
            channel: $channel,
            architecture: $request->architecture(),
        );

        $stats->recordCheck(
            platform: (string) $request->platform(),
            channel: $channel,
            clientVersion: $request->version(),
            architecture: $request->architecture(),
            country: $stats->countryFrom($request),
        );

        return new CheckResultResource($result);
    }
}
