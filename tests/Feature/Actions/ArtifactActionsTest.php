<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use TecClub\ReleaseKit\Actions\AttachArtifact;
use TecClub\ReleaseKit\Actions\DeleteArtifact;
use TecClub\ReleaseKit\Actions\UpdateArtifact;
use TecClub\ReleaseKit\Events\ArtifactAttached;
use TecClub\ReleaseKit\Exceptions\InvalidArtifactFile;
use TecClub\ReleaseKit\Exceptions\ReleaseLocked;
use TecClub\ReleaseKit\Exceptions\UnsupportedValue;
use TecClub\ReleaseKit\Jobs\DeleteArtifactFile;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Models\Release;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('private');
    config()->set('filesystems.default', 'local');
});

describe('AttachArtifact', function () {
    it('streams an upload to its final path', function () {
        Event::fake([ArtifactAttached::class]);
        $release = Release::factory()->create(['platform' => 'windows', 'channel' => 'stable', 'version' => '1.2.0']);

        $artifact = app(AttachArtifact::class)->handle(
            $release,
            UploadedFile::fake()->create('My App Setup.exe', 2048),
            format: 'exe',
            architecture: 'x64',
            label: 'Installer',
            sortOrder: 3,
        );

        expect($artifact->path)->toMatch('#^releasekit/stable/windows/1\.2\.0/[0-9a-z]{26}-my-app-setup\.exe$#')
            ->and($artifact->original_filename)->toBe('My App Setup.exe')
            ->and($artifact->disk)->toBe('local')
            ->and($artifact->size_bytes)->toBe(2048 * 1024)
            ->and($artifact->architecture)->toBe('x64')
            ->and($artifact->label)->toBe('Installer')
            ->and($artifact->sort_order)->toBe(3)
            ->and($artifact->is_active)->toBeTrue();

        Storage::disk('local')->assertExists($artifact->path);
        Event::assertDispatched(ArtifactAttached::class);
    });

    it('moves a file that was uploaded directly to storage', function () {
        Storage::disk('local')->put('uploads/tmp/build.apk', 'apk-bytes');
        $release = Release::factory()->create(['version' => '2.0.0']);

        $artifact = app(AttachArtifact::class)->handle($release, 'uploads/tmp/build.apk', format: 'apk');

        expect($artifact->path)->toStartWith('releasekit/stable/android/2.0.0/')
            ->and($artifact->original_filename)->toBe('build.apk')
            ->and($artifact->size_bytes)->toBe(9);

        Storage::disk('local')->assertMissing('uploads/tmp/build.apk');
        expect(Storage::disk('local')->get($artifact->path))->toBe('apk-bytes');
    });

    it('stores files on the channel disk', function () {
        config()->set('releasekit.channels.beta.disk', 'private');
        $release = Release::factory()->create(['channel' => 'beta']);

        $artifact = app(AttachArtifact::class)->handle($release, UploadedFile::fake()->create('app.apk', 1), format: 'apk');

        expect($artifact->disk)->toBe('private');
        Storage::disk('private')->assertExists($artifact->path);
    });

    it('keeps compound extensions', function () {
        $release = Release::factory()->create(['platform' => 'linux']);

        $artifact = app(AttachArtifact::class)->handle($release, UploadedFile::fake()->create('App_Linux.TAR.GZ', 1), format: 'tar.gz');

        expect($artifact->path)->toEndWith('-app-linux.tar.gz');
    });

    it('validates format and architecture against the platform', function (array $input) {
        $release = Release::factory()->create(['platform' => 'android']);

        app(AttachArtifact::class)->handle($release, UploadedFile::fake()->create('a.apk', 1), ...$input);
    })->with([
        'format' => [['format' => 'exe']],
        'architecture' => [['format' => 'apk', 'architecture' => 'x64']],
    ])->throws(UnsupportedValue::class);

    it('enforces the maximum upload size for both inputs', function (bool $upload) {
        config()->set('releasekit.storage.max_upload_size_mb', 1);
        $release = Release::factory()->create();

        if ($upload) {
            $file = UploadedFile::fake()->create('big.apk', 1025);
        } else {
            Storage::disk('local')->put('uploads/big.apk', str_repeat('x', 1024 * 1024 + 1));
            $file = 'uploads/big.apk';
        }

        expect(fn () => app(AttachArtifact::class)->handle($release, $file, format: 'apk'))->toThrow(InvalidArtifactFile::class)
            ->and(Artifact::count())->toBe(0);
    })->with(['upload' => true, 'path' => false]);

    it('rejects a path that does not exist', function () {
        app(AttachArtifact::class)->handle(Release::factory()->create(), 'uploads/missing.apk', format: 'apk');
    })->throws(InvalidArtifactFile::class, 'does not exist');

    it('can attach to published releases but not yanked ones', function () {
        $published = Release::factory()->published()->create();
        app(AttachArtifact::class)->handle($published, UploadedFile::fake()->create('a.apk', 1), format: 'apk');

        app(AttachArtifact::class)->handle(Release::factory()->yanked()->create(), UploadedFile::fake()->create('a.apk', 1), format: 'apk');
    })->throws(ReleaseLocked::class);

    it('removes the stored file when the database write fails', function () {
        $release = Release::factory()->create();
        Artifact::creating(fn () => throw new RuntimeException('db down'));

        expect(fn () => app(AttachArtifact::class)->handle($release, UploadedFile::fake()->create('a.apk', 1), format: 'apk'))
            ->toThrow(RuntimeException::class);

        expect(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('moves a direct upload back when the database write fails', function () {
        Storage::disk('local')->put('uploads/build.apk', 'x');
        Artifact::creating(fn () => throw new RuntimeException('db down'));

        expect(fn () => app(AttachArtifact::class)->handle(Release::factory()->create(), 'uploads/build.apk', format: 'apk'))
            ->toThrow(RuntimeException::class);

        expect(Storage::disk('local')->allFiles())->toBe(['uploads/build.apk']);
    });
});

describe('UpdateArtifact', function () {
    it('updates descriptive fields', function () {
        $artifact = Artifact::factory()->create();

        app(UpdateArtifact::class)->handle($artifact, [
            'architecture' => 'arm64-v8a',
            'label' => 'ARM build',
            'is_active' => false,
            'sort_order' => 5,
            'metadata' => ['min_sdk' => 26],
        ]);

        expect($artifact->fresh()->only(['architecture', 'label', 'is_active', 'sort_order', 'metadata']))->toBe([
            'architecture' => 'arm64-v8a',
            'label' => 'ARM build',
            'is_active' => false,
            'sort_order' => 5,
            'metadata' => ['min_sdk' => 26],
        ]);
    });

    it('validates the architecture', function () {
        app(UpdateArtifact::class)->handle(Artifact::factory()->create(), ['architecture' => 'x64']);
    })->throws(UnsupportedValue::class);

    it('does not allow changing the file or format', function (string $field) {
        app(UpdateArtifact::class)->handle(Artifact::factory()->create(), [$field => 'x']);
    })->with(['format', 'path', 'disk', 'app_release_id'])->throws(InvalidArgumentException::class);

    it('is locked on yanked releases', function () {
        $artifact = Artifact::factory()->for(Release::factory()->yanked())->create();

        app(UpdateArtifact::class)->handle($artifact, ['label' => 'x']);
    })->throws(ReleaseLocked::class);
});

describe('DeleteArtifact', function () {
    it('deletes the row and queues the file deletion after commit', function () {
        Bus::fake([DeleteArtifactFile::class]);
        $artifact = Artifact::factory()->for(Release::factory()->published())->create();

        app(DeleteArtifact::class)->handle($artifact);

        expect(Artifact::count())->toBe(0);
        Bus::assertDispatched(DeleteArtifactFile::class, fn ($job) => $job->disk === 'local' && $job->path === $artifact->path);
    });

    it('removes the file when the job runs', function () {
        $release = Release::factory()->create();
        $artifact = app(AttachArtifact::class)->handle($release, UploadedFile::fake()->create('a.apk', 1), format: 'apk');

        app(DeleteArtifact::class)->handle($artifact);

        Storage::disk('local')->assertMissing($artifact->path);
    });

    it('only logs a warning when the file cannot be deleted', function () {
        Log::spy();

        (new DeleteArtifactFile('missing-disk', 'releasekit/a.apk'))->handle(app('filesystem'));

        Log::shouldHaveReceived('warning')->once();
    });

    it('is locked on yanked releases', function () {
        app(DeleteArtifact::class)->handle(Artifact::factory()->for(Release::factory()->yanked())->create());
    })->throws(ReleaseLocked::class);
});
