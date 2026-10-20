<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use TecClub\ReleaseKit\Services\ChannelAccessGuard;

beforeEach(function () {
    $this->guard = app(ChannelAccessGuard::class);
});

function betaTester(bool $tester = true): Authenticatable
{
    return new GenericUser(['id' => 1, 'beta' => $tester]);
}

it('opens public channels to everyone', function () {
    expect($this->guard->allows('stable'))->toBeTrue();
});

it('denies protected channels when no ability is defined', function () {
    expect($this->guard->allows('beta', betaTester()))->toBeFalse();
});

it('asks the gate for protected channels', function () {
    Gate::define('releasekit.access-channel', fn (?Authenticatable $user, string $channel) => $channel === 'beta' && ($user?->beta ?? false));

    expect($this->guard->allows('beta'))->toBeFalse()
        ->and($this->guard->allows('beta', betaTester(false)))->toBeFalse()
        ->and($this->guard->allows('beta', betaTester()))->toBeTrue();
});

it('fails closed for unknown channels', function () {
    Gate::define('releasekit.access-channel', fn (?Authenticatable $user, string $channel) => $channel === 'beta');

    expect($this->guard->allows('nightly'))->toBeFalse();
});

it('uses the configured ability', function () {
    config()->set('releasekit.access.ability', 'see-betas');
    Gate::define('see-betas', fn (?Authenticatable $user) => true);

    expect($this->guard->allows('beta'))->toBeTrue();
});

it('resolves the user from the configured guard without auth middleware', function () {
    config()->set('auth.guards.api-token', ['driver' => 'session', 'provider' => 'users']);
    config()->set('releasekit.access.guard', 'api-token');
    Gate::define('releasekit.access-channel', fn (?Authenticatable $user) => $user !== null);
    Auth::guard('api-token')->setUser(betaTester());

    $request = Request::create('/check');
    $request->setUserResolver(fn (?string $guard = null) => Auth::guard($guard)->user());

    expect($this->guard->userFromRequest($request))->not->toBeNull()
        ->and($this->guard->allowsRequest($request, 'beta'))->toBeTrue();

    config()->set('releasekit.access.guard', 'web');

    expect($this->guard->allowsRequest($request, 'beta'))->toBeFalse();
});

it('throws an authorization exception (403) when denied', function () {
    $this->guard->authorize(Request::create('/check'), 'beta');
})->throws(AuthorizationException::class);
