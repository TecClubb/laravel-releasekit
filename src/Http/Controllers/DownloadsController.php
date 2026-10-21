<?php

namespace TecClub\ReleaseKit\Http\Controllers;

use TecClub\ReleaseKit\Http\Controllers\Concerns\AuthorizesChannels;
use TecClub\ReleaseKit\Http\Requests\DownloadsRequest;
use TecClub\ReleaseKit\Http\Resources\DownloadsResource;
use TecClub\ReleaseKit\Services\DownloadResolver;

/**
 * GET {prefix}/downloads[?platform=windows][&channel=beta][&architecture=x64]
 */
class DownloadsController
{
    use AuthorizesChannels;

    public function __invoke(DownloadsRequest $request, DownloadResolver $resolver): DownloadsResource
    {
        $channel = $this->authorizeChannel($request, $request->channel());

        return new DownloadsResource($resolver->all($channel, $request->platform(), $request->architecture()));
    }
}
