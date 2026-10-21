<?php

namespace TecClub\ReleaseKit\Http\Controllers\Concerns;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use TecClub\ReleaseKit\Services\ChannelAccessGuard;
use TecClub\ReleaseKit\Support\ChannelRegistry;

trait AuthorizesChannels
{
    /**
     * Resolves the requested channel (or the default) and answers with a
     * JSON 403 when it is protected and the caller lacks access.
     */
    protected function authorizeChannel(Request $request, ?string $channel): string
    {
        $channel ??= app(ChannelRegistry::class)->default();

        if (! app(ChannelAccessGuard::class)->allowsRequest($request, $channel)) {
            throw new HttpResponseException(response()->json([
                'message' => "Access to the {$channel} channel is not allowed.",
            ], 403));
        }

        return $channel;
    }
}
