<?php

namespace TecClub\ReleaseKit\Services;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Routing\UrlGenerator;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use TecClub\ReleaseKit\Data\ArtifactData;
use TecClub\ReleaseKit\Enums\DeliveryMode;
use TecClub\ReleaseKit\Exceptions\MissingDownloadRoute;
use TecClub\ReleaseKit\Support\ChannelRegistry;

/**
 * Builds the URL clients use to download an artifact. Called after the
 * cache read, so signed URLs never expire inside the cache.
 *
 * - Protected channels: always a temporary signed route URL.
 * - "public" mode with public_through_app = false: the storage URL itself.
 * - Everything else: the app's download route, which delivers the file.
 *
 * Uses the route named config('releasekit.routes.name').'artifacts.download'.
 * Hosts that rename it bind their own generator in the container.
 */
class DownloadUrlGenerator
{
    public function __construct(
        private readonly Repository $config,
        private readonly UrlGenerator $urls,
        private readonly ChannelRegistry $channels,
        private readonly ArtifactDelivery $delivery,
    ) {}

    public function url(ArtifactData $artifact, string $channel): string
    {
        $parameters = ['artifact' => $artifact->id];

        if ($this->channels->isProtected($channel)) {
            $ttl = (int) $this->config->get('releasekit.delivery.signed_url_ttl', 300);

            return $this->route(fn () => $this->urls->temporarySignedRoute($this->routeName(), now()->addSeconds($ttl), $parameters));
        }

        if ($this->bypassesApp($artifact->disk, $channel)) {
            return $this->delivery->publicUrl($artifact);
        }

        return $this->route(fn () => $this->urls->route($this->routeName(), $parameters));
    }

    public function routeName(): string
    {
        return $this->config->get('releasekit.routes.name', 'releasekit.').'artifacts.download';
    }

    private function bypassesApp(string $disk, string $channel): bool
    {
        return ! $this->config->get('releasekit.delivery.public_through_app', true)
            && $this->delivery->modeFor($disk, $channel) === DeliveryMode::Public;
    }

    /**
     * @param  Closure(): string  $generate
     */
    private function route(Closure $generate): string
    {
        try {
            return $generate();
        } catch (RouteNotFoundException) {
            throw MissingDownloadRoute::named($this->routeName());
        }
    }
}
