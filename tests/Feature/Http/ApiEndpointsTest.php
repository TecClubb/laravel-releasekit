<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use TecClub\ReleaseKit\Actions\UpdateTrackMinimum;
use TecClub\ReleaseKit\Actions\YankRelease;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Link;
use TecClub\ReleaseKit\Models\Release;

beforeEach(function () {
    config()->set('cache.default', 'array');
    withReleaseKitRoutes();
});

function allowBetaFor(?Authenticatable $user): void
{
    Gate::define('releasekit.access-channel', fn (?Authenticatable $user, string $channel) => $user !== null);

    if ($user !== null) {
        test()->actingAs($user);
    }
}

describe('GET check', function () {
    it('returns the version check', function () {
        $release = currentRelease('1.2.0', ['build_number' => '45']);
        Artifact::factory()->for($release)->architecture('arm64-v8a')->create();
        Artifact::factory()->for($release)->architecture('x86_64')->create();
        app(UpdateTrackMinimum::class)->handle('android', 'stable', '1.0.0');

        $this->getJson('/api/releasekit/check?platform=android&version=1.1.0&architecture=arm64-v8a')
            ->assertOk()
            ->assertJsonPath('status', 'update_available')
            ->assertJsonPath('client_version', '1.1.0')
            ->assertJsonPath('minimum_supported_version', '1.0.0')
            ->assertJsonPath('current.version', '1.2.0')
            ->assertJsonPath('current.build_number', '45')
            ->assertJsonCount(1, 'downloads.artifacts')
            ->assertJsonMissingPath('data');
    });

    it('reports blocked and update_required', function () {
        currentRelease('2.0.0');
        app(YankRelease::class)->handle(Release::factory()->published()->create(['version' => '1.5.0']));
        app(UpdateTrackMinimum::class)->handle('android', 'stable', '1.0');

        $this->getJson('/api/releasekit/check?platform=android&version=1.5.0')->assertJsonPath('status', 'blocked');
        $this->getJson('/api/releasekit/check?platform=android&version=0.9')->assertJsonPath('status', 'update_required');
    });

    it('answers 422 with JSON even without an Accept header', function (string $query, string $field) {
        $this->get("/api/releasekit/check?{$query}")
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonValidationErrors($field);
    })->with([
        'missing platform' => ['version=1.0', 'platform'],
        'unknown platform' => ['platform=symbian&version=1.0', 'platform'],
        'missing version' => ['platform=android', 'version'],
        'invalid version' => ['platform=android&version=1.0.0-beta', 'version'],
        'build suffix' => ['platform=android&version=1.0.0%2B45', 'version'],
        'unknown channel' => ['platform=android&version=1.0&channel=nightly', 'channel'],
        'architecture of another platform' => ['platform=android&version=1.0&architecture=x64', 'architecture'],
    ]);

    it('denies a protected channel without access', function () {
        $this->get('/api/releasekit/check?platform=android&version=1.0&channel=beta')
            ->assertForbidden()
            ->assertJsonPath('message', 'Access to the beta channel is not allowed.');
    });

    it('serves a protected channel to users the gate allows', function () {
        currentRelease('2.0.0', ['channel' => 'beta']);
        allowBetaFor(new GenericUser(['id' => 1]));

        $this->getJson('/api/releasekit/check?platform=android&version=1.0&channel=beta')
            ->assertOk()
            ->assertJsonPath('status', 'update_available');
    });
});

describe('GET downloads', function () {
    it('returns current downloads per platform', function () {
        $release = currentRelease('1.1.3', ['platform' => 'windows']);
        $artifact = Artifact::factory()->for($release)->architecture('x64')->create(['format' => 'exe']);
        Link::factory()->create(['platform' => 'windows', 'store' => 'microsoft_store']);

        $this->getJson('/api/releasekit/downloads')
            ->assertOk()
            ->assertJsonPath('channel', 'stable')
            ->assertJsonPath('platforms.windows.label', 'Windows')
            ->assertJsonPath('platforms.windows.current.version', '1.1.3')
            ->assertJsonPath('platforms.windows.artifacts.0.url', route('releasekit.artifacts.download', $artifact->id))
            ->assertJsonPath('platforms.windows.links.0.label', 'Microsoft Store')
            ->assertJsonMissingPath('platforms.windows.artifacts.0.path')
            ->assertJsonMissingPath('platforms.windows.artifacts.0.disk');
    });

    it('returns an empty object when nothing is offered', function () {
        expect($this->getJson('/api/releasekit/downloads')->assertOk()->getContent())
            ->toBe('{"channel":"stable","platforms":{}}');
    });

    it('filters by platform and validates input', function () {
        Link::factory()->create(['platform' => 'android']);
        Link::factory()->create(['platform' => 'ios', 'store' => 'app_store']);

        $this->getJson('/api/releasekit/downloads?platform=ios')->assertJsonCount(1, 'platforms')->assertJsonPath('platforms.ios.label', 'iOS');
        $this->getJson('/api/releasekit/downloads?platform=symbian')->assertStatus(422);
        $this->getJson('/api/releasekit/downloads?channel=nightly')->assertStatus(422);
    });

    it('requires access for protected channels and returns signed URLs', function () {
        Artifact::factory()->for(currentRelease('2.0.0', ['channel' => 'beta']))->create();

        $this->getJson('/api/releasekit/downloads?channel=beta')->assertForbidden();

        allowBetaFor(new GenericUser(['id' => 1]));

        expect($this->getJson('/api/releasekit/downloads?channel=beta')->assertOk()->json('platforms.android.artifacts.0.url'))
            ->toContain('signature=');
    });
});

describe('GET releases', function () {
    it('pages published releases newest first without internal fields', function () {
        Release::factory()->published()->create(['version' => '1.0.0', 'published_at' => now()->subDays(2), 'release_notes' => 'First']);
        Release::factory()->published()->create(['version' => '1.1.0', 'published_at' => now()->subDay()]);
        Release::factory()->yanked('secret reason')->create(['version' => '1.0.5']);
        Release::factory()->create(['version' => '1.2.0']);

        $response = $this->getJson('/api/releasekit/releases?platform=android&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.version', '1.1.0')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1);

        expect(array_keys($response->json('data.0')))->toBe(['version', 'build_number', 'release_notes', 'published_at'])
            ->and($response->getContent())->not->toContain('secret reason');

        $this->getJson('/api/releasekit/releases?platform=android&per_page=1&page=2')->assertJsonPath('data.0.release_notes', 'First');
    });

    it('validates paging and access', function () {
        $this->getJson('/api/releasekit/releases?per_page=101')->assertStatus(422)->assertJsonValidationErrors('per_page');
        $this->getJson('/api/releasekit/releases?channel=beta')->assertForbidden();
    });
});
