<?php

use Illuminate\Support\Facades\Storage;
use TecClub\ReleaseKit\Models\Artifact;

beforeEach(function () {
    config()->set('filesystems.default', 'local');
    Storage::fake('local');
    Storage::fake('private');

    Storage::disk('local')->put('releasekit/stable/android/1.0.0/kept.apk', 'kept');
    Storage::disk('local')->put('releasekit/stable/android/1.0.0/orphan.apk', 'orphan');
    Storage::disk('local')->put('elsewhere/not-ours.txt', 'host file');

    Artifact::factory()->create(['disk' => 'local', 'path' => 'releasekit/stable/android/1.0.0/kept.apk']);

    ageFiles('local', 'private');
});

/**
 * Pushes every file's mtime past the default 60 minute grace period.
 */
function ageFiles(string ...$disks): void
{
    foreach ($disks as $disk) {
        foreach (Storage::disk($disk)->allFiles() as $path) {
            touch(Storage::disk($disk)->path($path), now()->subHours(2)->getTimestamp());
        }
    }
}

it('lists orphans without deleting them on a dry run', function () {
    $this->artisan('releasekit:prune-orphans', ['--dry-run' => true])
        ->expectsOutputToContain('releasekit/stable/android/1.0.0/orphan.apk')
        ->expectsOutputToContain('1 orphaned file(s), 6 B. Dry run: nothing was deleted.')
        ->assertSuccessful();

    Storage::disk('local')->assertExists('releasekit/stable/android/1.0.0/orphan.apk');
});

it('deletes only orphans under the releasekit directory', function () {
    $this->artisan('releasekit:prune-orphans', ['--force' => true])->assertSuccessful();

    Storage::disk('local')->assertMissing('releasekit/stable/android/1.0.0/orphan.apk');
    Storage::disk('local')->assertExists('releasekit/stable/android/1.0.0/kept.apk');
    Storage::disk('local')->assertExists('elsewhere/not-ours.txt');
});

it('asks for confirmation unless forced', function () {
    $this->artisan('releasekit:prune-orphans')
        ->expectsConfirmation('Delete 1 orphaned file(s), 6 B.', 'no')
        ->expectsOutputToContain('Nothing was deleted')
        ->assertSuccessful();

    Storage::disk('local')->assertExists('releasekit/stable/android/1.0.0/orphan.apk');
});

it('skips recently modified files so in-flight attaches are safe', function () {
    Storage::disk('local')->put('releasekit/stable/android/1.0.1/just-moved.apk', 'new');

    $this->artisan('releasekit:prune-orphans', ['--force' => true])->assertSuccessful();
    Storage::disk('local')->assertExists('releasekit/stable/android/1.0.1/just-moved.apk');

    $this->artisan('releasekit:prune-orphans', ['--force' => true, '--older-than' => 0])->assertSuccessful();
    Storage::disk('local')->assertMissing('releasekit/stable/android/1.0.1/just-moved.apk');
});

it('scans channel disks too', function () {
    config()->set('releasekit.channels.beta.disk', 'private');
    Storage::disk('private')->put('releasekit/beta/android/2.0.0/orphan.apk', 'x');
    ageFiles('private');

    $this->artisan('releasekit:prune-orphans', ['--dry-run' => true])
        ->expectsOutputToContain('releasekit/beta/android/2.0.0/orphan.apk')
        ->expectsOutputToContain('2 orphaned file(s)');
});

it('can be limited to specific disks', function () {
    config()->set('releasekit.channels.beta.disk', 'private');
    Storage::disk('private')->put('releasekit/beta/orphan.apk', 'x');
    ageFiles('private');

    $this->artisan('releasekit:prune-orphans', ['--force' => true, '--disk' => ['private']])->assertSuccessful();

    Storage::disk('private')->assertMissing('releasekit/beta/orphan.apk');
    Storage::disk('local')->assertExists('releasekit/stable/android/1.0.0/orphan.apk');
});

it('reports when there is nothing to prune', function () {
    Storage::disk('local')->delete('releasekit/stable/android/1.0.0/orphan.apk');

    $this->artisan('releasekit:prune-orphans')
        ->expectsOutputToContain('No orphaned files found')
        ->assertSuccessful();
});
