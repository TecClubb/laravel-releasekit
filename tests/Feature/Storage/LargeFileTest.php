<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use TecClub\ReleaseKit\Actions\AttachArtifact;
use TecClub\ReleaseKit\Actions\DeleteArtifact;
use TecClub\ReleaseKit\Models\Release;

/*
 * Done-when for phase 5: a 1 GB+ file attaches without memory issues.
 * The file is sparse where the filesystem allows it, so it costs little disk.
 */

const LARGE_FILE_BYTES = 1100 * 1024 * 1024;
const MEMORY_BUDGET_BYTES = 32 * 1024 * 1024;

beforeEach(function () {
    config()->set('filesystems.default', 'local');
    Storage::fake('local');
});

function makeLargeFile(string $path): void
{
    $handle = fopen($path, 'w');
    ftruncate($handle, LARGE_FILE_BYTES);
    fseek($handle, LARGE_FILE_BYTES - 4);
    fwrite($handle, 'END!');
    fclose($handle);
}

it('attaches a 1 GB+ upload with flat memory and checksums it', function () {
    $source = tempnam(sys_get_temp_dir(), 'rk');
    makeLargeFile($source);

    memory_reset_peak_usage();
    $before = memory_get_usage();

    $artifact = app(AttachArtifact::class)->handle(
        Release::factory()->create(['platform' => 'windows']),
        new UploadedFile($source, 'Big Setup.exe', 'application/octet-stream', null, true),
        format: 'exe',
    );

    $peak = memory_get_peak_usage() - $before;
    $artifact->refresh();

    expect($peak)->toBeLessThan(MEMORY_BUDGET_BYTES)
        ->and($artifact->size_bytes)->toBe(LARGE_FILE_BYTES)
        ->and($artifact->checksum_sha256)->toHaveLength(64)
        ->and(Storage::disk('local')->size($artifact->path))->toBe(LARGE_FILE_BYTES);

    app(DeleteArtifact::class)->handle($artifact);
    Storage::disk('local')->assertMissing($artifact->path);
    @unlink($source);
})->group('large-files');

it('attaches a 1 GB+ file already on the disk with flat memory', function () {
    Storage::disk('local')->makeDirectory('uploads');
    makeLargeFile(Storage::disk('local')->path('uploads/big.AppImage'));

    memory_reset_peak_usage();
    $before = memory_get_usage();

    $artifact = app(AttachArtifact::class)->handle(
        Release::factory()->create(['platform' => 'linux']),
        'uploads/big.AppImage',
        format: 'appimage',
    );

    $peak = memory_get_peak_usage() - $before;
    $artifact->refresh();

    expect($peak)->toBeLessThan(MEMORY_BUDGET_BYTES)
        ->and($artifact->size_bytes)->toBe(LARGE_FILE_BYTES)
        ->and($artifact->checksum_sha256)->toHaveLength(64);

    Storage::disk('local')->assertMissing('uploads/big.AppImage');
    Storage::disk('local')->delete($artifact->path);
})->group('large-files');
