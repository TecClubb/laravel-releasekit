<?php

namespace TecClub\ReleaseKit\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use TecClub\ReleaseKit\Support\ChannelRegistry;

/**
 * Public channels are open to anyone. Protected channels require the
 * configured Gate ability:
 *
 *     Gate::define('releasekit.access-channel', fn (?User $user, string $channel) => ...);
 *
 * The user is resolved from the configured guard without requiring auth
 * middleware, so the same routes serve public and protected channels.
 * An undefined ability denies access.
 */
class ChannelAccessGuard
{
    public function __construct(
        private readonly Repository $config,
        private readonly ChannelRegistry $channels,
        private readonly Gate $gate,
    ) {}

    public function allows(string $channel, ?Authenticatable $user = null): bool
    {
        if ($this->channels->isPublic($channel)) {
            return true;
        }

        return $this->gate->forUser($user)->allows($this->ability(), [$channel]);
    }

    public function allowsRequest(Request $request, string $channel): bool
    {
        return $this->allows($channel, $this->userFromRequest($request));
    }

    /**
     * @throws AuthorizationException
     */
    public function authorize(Request $request, string $channel): void
    {
        if (! $this->allowsRequest($request, $channel)) {
            throw new AuthorizationException("Access to the {$channel} channel is not allowed.");
        }
    }

    public function userFromRequest(Request $request): ?Authenticatable
    {
        $user = $request->user($this->config->get('releasekit.access.guard'));

        return $user instanceof Authenticatable ? $user : null;
    }

    private function ability(): string
    {
        return (string) $this->config->get('releasekit.access.ability', 'releasekit.access-channel');
    }
}
