<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use TecClub\ReleaseKit\Actions\AttachArtifact;
use TecClub\ReleaseKit\Jobs\ComputeArtifactChecksum;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Release;
use TecClub\ReleaseKit\Services\VersionChecker;

beforeEach(function () {
    config()->set('filesystems.default', 'local');
    Storage::fake('local');
});

function runChecksum(Artifact $artifact): Artifact
{
    (new ComputeArtifactChecksum($artifact->id))->handle(app('filesystem'));

    return $artifact->fresh();
}

it('is queued after commit when an artifact is attached', function () {
    Bus::fake([ComputeArtifactChecksum::class]);

    $artifact = app(AttachArtifact::class)->handle(Release::factory()->create(), UploadedFile::fake()->create('a.apk', 1), format: 'apk');

    Bus::assertDispatched(ComputeArtifactChecksum::class, fn ($job) => $job->artifactId === $artifact->id);
});

it('fills checksum, size and MIME type from the stored file', function () {
    Storage::disk('local')->put('releasekit/app.apk', 'APK-BYTES');
    $artifact = Artifact::factory()->create([
        'disk' => 'local', 'path' => 'releasekit/app.apk',
        'checksum_sha256' => null, 'size_bytes' => null, 'mime_type' => null,
    ]);

    $artifact = runChecksum($artifact);

    expect($artifact->checksum_sha256)->toBe(hash('sha256', 'APK-BYTES'))
        ->and($artifact->size_bytes)->toBe(9)
        ->and($artifact->mime_type)->not->toBeNull();
});

it('keeps values that are already set', function () {
    Storage::disk('local')->put('releasekit/app.apk', 'APK-BYTES');
    $artifact = Artifact::factory()->create([
        'disk' => 'local', 'path' => 'releasekit/app.apk',
        'checksum_sha256' => null, 'size_bytes' => 123, 'mime_type' => 'application/custom',
    ]);

    $artifact = runChecksum($artifact);

    expect($artifact->checksum_sha256)->toBe(hash('sha256', 'APK-BYTES'))
        ->and($artifact->size_bytes)->toBe(123)
        ->and($artifact->mime_type)->toBe('application/custom');
});

it('does nothing when the artifact was deleted', function () {
    (new ComputeArtifactChecksum(999))->handle(app('filesystem'));
})->throwsNoExceptions();

it('logs a warning when the file is missing', function () {
    Log::spy();
    $artifact = Artifact::factory()->create(['disk' => 'local', 'checksum_sha256' => null]);

    expect(runChecksum($artifact)->checksum_sha256)->toBeNull();
    Log::shouldHaveReceived('warning')->once();
});

it('runs on the queue and the served checksum updates', function () {
    config()->set('cache.default', 'array');
    registerDownloadRoute();
    $release = currentRelease('1.0.0');
    Storage::disk('local')->put('uploads/build.apk', 'REAL-BUILD');

    // The sync queue runs the job right after the attach commits.
    app(AttachArtifact::class)->handle($release, 'uploads/build.apk', format: 'apk');

    expect(app(VersionChecker::class)->check('android', '0.9')->downloads->artifacts[0]->artifact->checksumSha256)
        ->toBe(hash('sha256', 'REAL-BUILD'));
});
