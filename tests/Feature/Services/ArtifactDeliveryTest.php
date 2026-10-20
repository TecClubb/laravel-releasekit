<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use TecClub\ReleaseKit\Enums\DeliveryMode;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Services\ArtifactDelivery;

beforeEach(function () {
    ArtifactDelivery::resetWarnings();

    // "files": no public URL, no temporary URLs. "signing": temporary URLs. "cdn": public URL + temporary URLs.
    // Storage::fake() always adds a temporary URL callback, so build this one directly.
    Storage::set('files', Storage::build(['driver' => 'local', 'root' => storage_path('framework/testing/disks/files'), 'serve' => false]));
    Storage::fake('signing');
    Storage::disk('signing')->buildTemporaryUrlsUsing(
        fn (string $path, DateTimeInterface $expiration, array $options) => "https://signed.example.com/{$path}?expires={$expiration->getTimestamp()}&disposition=".rawurlencode($options['ResponseContentDisposition'] ?? '')
    );
    Storage::fake('cdn', ['url' => 'https://cdn.example.com']);
    Storage::disk('cdn')->buildTemporaryUrlsUsing(fn (string $path) => "https://cdn.example.com/{$path}?signed=1");

    foreach (['files', 'signing', 'cdn'] as $disk) {
        Storage::disk($disk)->put('releasekit/app.apk', 'APK-BYTES');
    }

    $this->delivery = app(ArtifactDelivery::class);
});

function artifactOn(string $disk, string $channel = 'stable'): Artifact
{
    return Artifact::factory()
        ->for(Release::factory()->published()->state(['channel' => $channel]))
        ->create([
            'disk' => $disk,
            'path' => 'releasekit/app.apk',
            'original_filename' => 'My App 1.0.apk',
            'mime_type' => 'application/vnd.android.package-archive',
            'size_bytes' => 9,
        ]);
}

it('streams the file with the right headers', function () {
    config()->set('releasekit.delivery.mode', 'stream');

    $response = $this->delivery->deliver(artifactOn('signing'));

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('Content-Type'))->toBe('application/vnd.android.package-archive')
        ->and($response->headers->get('Content-Length'))->toBe('9')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('My App 1.0.apk');

    ob_start();
    $response->sendContent();
    expect(ob_get_clean())->toBe('APK-BYTES');
});

it('redirects to a temporary storage URL in signed mode', function () {
    config()->set('releasekit.delivery.mode', 'signed');
    $this->freezeTime();

    $response = $this->delivery->deliver(artifactOn('signing'));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))
        ->toStartWith('https://signed.example.com/releasekit/app.apk?expires='.now()->addSeconds(300)->getTimestamp())
        ->toContain(rawurlencode('attachment; filename="My App 1.0.apk"'));
});

it('redirects to the public storage URL in public mode', function () {
    config()->set('releasekit.delivery.mode', 'public');

    $response = $this->delivery->deliver(artifactOn('cdn'));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toBe('https://cdn.example.com/releasekit/app.apk');
});

it('never uses public delivery for protected channels', function () {
    config()->set('releasekit.delivery.mode', 'public');

    $response = $this->delivery->deliver(artifactOn('cdn', channel: 'beta'));

    expect($this->delivery->modeFor('cdn', 'beta'))->toBe(DeliveryMode::Signed)
        ->and($response->headers->get('Location'))->toBe('https://cdn.example.com/releasekit/app.apk?signed=1');
});

it('falls back from signed to stream when the disk cannot sign, warning once', function () {
    config()->set('releasekit.delivery.mode', 'signed');
    Log::spy();

    expect($this->delivery->deliver(artifactOn('files')))->toBeInstanceOf(StreamedResponse::class)
        ->and($this->delivery->modeFor('files', 'stable'))->toBe(DeliveryMode::Stream);

    Log::shouldHaveReceived('warning')->once();
});

it('falls back from public to signed, then to stream', function () {
    config()->set('releasekit.delivery.mode', 'public');

    expect($this->delivery->modeFor('signing', 'stable'))->toBe(DeliveryMode::Signed)
        ->and($this->delivery->modeFor('files', 'stable'))->toBe(DeliveryMode::Stream)
        ->and($this->delivery->modeFor('cdn', 'stable'))->toBe(DeliveryMode::Public);
});

it('treats an unknown configured mode as signed', function () {
    config()->set('releasekit.delivery.mode', 'carrier-pigeon');

    expect($this->delivery->configuredMode())->toBe(DeliveryMode::Signed);
});

it('uses an ASCII fallback filename for non-ASCII names', function () {
    config()->set('releasekit.delivery.mode', 'stream');
    $artifact = artifactOn('files');
    $artifact->update(['original_filename' => 'Приложение.apk']);

    expect($this->delivery->deliver($artifact)->headers->get('Content-Disposition'))
        ->toContain('filename=')
        ->toContain("filename*=utf-8''");
});
