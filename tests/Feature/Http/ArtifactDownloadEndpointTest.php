<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use TecClub\ReleaseKit\Enums\DeliveryMode;
use TecClub\ReleaseKit\Events\ArtifactDownloaded;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Release;

beforeEach(function () {
    config()->set('cache.default', 'array');
    config()->set('filesystems.default', 'local');
    Storage::fake('local');
    Storage::disk('local')->put('releasekit/app.apk', 'APK-BYTES');
    withReleaseKitRoutes();
});

function downloadable(array $releaseState = [], array $attributes = []): Artifact
{
    return Artifact::factory()
        ->for(Release::factory()->published()->state($releaseState))
        ->create(['disk' => 'local', 'path' => 'releasekit/app.apk', 'original_filename' => 'app.apk', 'size_bytes' => 9, ...$attributes]);
}

it('streams the file in stream mode and fires the download event', function () {
    Event::fake([ArtifactDownloaded::class]);
    config()->set('releasekit.delivery.mode', 'stream');
    $artifact = downloadable();

    $response = $this->get(route('releasekit.artifacts.download', $artifact->id))->assertOk();

    expect($response->streamedContent())->toBe('APK-BYTES')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment; filename=app.apk');

    Event::assertDispatched(ArtifactDownloaded::class, fn ($event) => $event->artifact->is($artifact) && $event->mode === DeliveryMode::Stream);
});

it('redirects to a temporary storage URL in signed mode', function () {
    config()->set('releasekit.delivery.mode', 'signed');

    $this->get(route('releasekit.artifacts.download', downloadable()->id))
        ->assertRedirect()
        ->assertRedirectContains('releasekit/app.apk?expiration=');
});

it('redirects to the public storage URL in public mode', function () {
    Storage::fake('cdn', ['url' => 'https://cdn.example.com']);
    config()->set('releasekit.delivery.mode', 'public');

    $this->get(route('releasekit.artifacts.download', downloadable(attributes: ['disk' => 'cdn'])->id))
        ->assertRedirect('https://cdn.example.com/releasekit/app.apk');
});

it('returns 404 for anything that is not offered', function (string $case) {
    $artifact = match ($case) {
        'inactive' => downloadable(attributes: ['is_active' => false]),
        'non-downloadable format' => downloadable(attributes: ['format' => 'aab']),
        'draft release' => Artifact::factory()->for(Release::factory())->create(),
        'yanked release' => Artifact::factory()->for(Release::factory()->yanked())->create(),
    };

    $this->get(route('releasekit.artifacts.download', $artifact->id))->assertNotFound();
})->with(['inactive', 'non-downloadable format', 'draft release', 'yanked release']);

it('returns 404 for unknown or non-numeric ids', function () {
    $this->get('/api/releasekit/artifacts/999/download')->assertNotFound();
    $this->get('/api/releasekit/artifacts/abc/download')->assertNotFound();
});

it('requires a valid signature for protected channels', function () {
    config()->set('releasekit.delivery.mode', 'stream');
    $artifact = downloadable(['channel' => 'beta']);

    $this->get(route('releasekit.artifacts.download', $artifact->id))->assertForbidden();

    $signed = URL::temporarySignedRoute('releasekit.artifacts.download', now()->addMinutes(5), ['artifact' => $artifact->id]);
    $this->get($signed)->assertOk();

    $this->travel(6)->minutes();
    $this->get($signed)->assertForbidden();
});

it('rejects a signature made for another artifact', function () {
    $artifact = downloadable(['channel' => 'beta']);
    $other = downloadable(['channel' => 'beta']);
    $signed = URL::temporarySignedRoute('releasekit.artifacts.download', now()->addMinutes(5), ['artifact' => $other->id]);

    $this->get(str_replace("/artifacts/{$other->id}/", "/artifacts/{$artifact->id}/", $signed))->assertForbidden();
});

it('downloads end to end from the URL the downloads endpoint returns', function () {
    config()->set('releasekit.delivery.mode', 'stream');
    Gate::define('releasekit.access-channel', fn ($user) => $user !== null);
    $release = currentRelease('1.0.0', ['channel' => 'beta']);
    Artifact::factory()->for($release)->create(['disk' => 'local', 'path' => 'releasekit/app.apk']);

    $this->actingAs(new GenericUser(['id' => 1]));
    $url = $this->getJson('/api/releasekit/downloads?channel=beta')->json('platforms.android.artifacts.0.url');

    // The signed link works without the session, e.g. in a download manager.
    $this->app['auth']->forgetGuards();
    expect($this->get($url)->assertOk()->streamedContent())->toBe('APK-BYTES');
});
