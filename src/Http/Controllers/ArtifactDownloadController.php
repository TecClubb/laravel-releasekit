<?php

namespace TecClub\ReleaseKit\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use TecClub\ReleaseKit\Events\ArtifactDownloaded;
use TecClub\ReleaseKit\Services\ArtifactDelivery;
use TecClub\ReleaseKit\Services\DownloadResolver;
use TecClub\ReleaseKit\Support\ChannelRegistry;
use TecClub\ReleaseKit\Support\ModelResolver;

/**
 * GET {prefix}/artifacts/{artifact}/download
 *
 * 404 unless the artifact is active, downloadable and its release is
 * published. Protected channels require a valid signed URL (403 otherwise);
 * the caller was authorised when the downloads list was requested.
 */
class ArtifactDownloadController
{
    public function __invoke(
        Request $request,
        string $artifact,
        DownloadResolver $resolver,
        ArtifactDelivery $delivery,
        ChannelRegistry $channels,
    ): Response {
        $model = ModelResolver::artifact()::query()->with('release')->find($artifact);

        if ($model === null || ! $resolver->isDownloadable($model)) {
            abort(404);
        }

        $channel = $model->release->channel;

        if ($channels->isProtected($channel) && ! $request->hasValidSignature()) {
            abort(403, 'This download link is invalid or has expired.');
        }

        $mode = $delivery->modeFor($model->disk, $channel);

        ArtifactDownloaded::dispatch($model, $mode);

        return $delivery->deliver($model);
    }
}
