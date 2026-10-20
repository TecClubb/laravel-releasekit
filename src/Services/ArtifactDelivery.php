<?php

namespace TecClub\ReleaseKit\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use TecClub\ReleaseKit\Data\ArtifactData;
use TecClub\ReleaseKit\Enums\DeliveryMode;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Support\ChannelRegistry;
use Throwable;

/**
 * Turns an artifact into an HTTP response according to the delivery mode:
 *
 * - stream: the app streams the file (never loaded fully into memory)
 * - signed: 302 to a temporary storage URL
 * - public: 302 to the public storage URL
 *
 * Protected channels never use "public". If the disk can't provide what a
 * mode needs, it falls back: public → signed → stream (warning logged once).
 */
class ArtifactDelivery
{
    /** @var array<string, true> */
    private static array $warned = [];

    public function __construct(
        private readonly Repository $config,
        private readonly Factory $filesystem,
        private readonly ChannelRegistry $channels,
    ) {}

    public function deliver(Artifact $artifact): Response
    {
        $data = ArtifactData::fromModel($artifact);
        $channel = $artifact->release->channel;

        return match ($this->modeFor($data->disk, $channel)) {
            DeliveryMode::Stream => $this->stream($data),
            DeliveryMode::Signed => new RedirectResponse($this->temporaryUrl($data)),
            DeliveryMode::Public => new RedirectResponse($this->publicUrl($data)),
        };
    }

    /**
     * The mode actually used for a disk and channel, after access rules and fallbacks.
     */
    public function modeFor(string $disk, string $channel): DeliveryMode
    {
        $mode = $this->configuredMode();

        if ($mode === DeliveryMode::Public && ! $this->channels->isPublic($channel)) {
            $mode = DeliveryMode::Signed;
        }

        if ($mode === DeliveryMode::Public && ! $this->supportsPublicUrls($disk)) {
            $this->warnOnce($disk, 'public', 'The disk has no public URL; falling back to signed delivery.');
            $mode = DeliveryMode::Signed;
        }

        if ($mode === DeliveryMode::Signed && ! $this->supportsTemporaryUrls($disk)) {
            $this->warnOnce($disk, 'signed', 'The disk cannot create temporary URLs; falling back to streaming.');
            $mode = DeliveryMode::Stream;
        }

        return $mode;
    }

    public function configuredMode(): DeliveryMode
    {
        return DeliveryMode::tryFrom((string) $this->config->get('releasekit.delivery.mode', 'signed')) ?? DeliveryMode::Signed;
    }

    public function publicUrl(ArtifactData $artifact): string
    {
        return $this->disk($artifact->disk)->url($artifact->path);
    }

    public function supportsPublicUrls(string $disk): bool
    {
        $filesystem = $this->disk($disk);

        if (! $filesystem instanceof FilesystemAdapter) {
            return false;
        }

        // A local disk without a "url" falls back to /storage/..., which is only public for the "public" disk setup.
        if ($filesystem->getAdapter() instanceof LocalFilesystemAdapter) {
            return ! empty($filesystem->getConfig()['url']);
        }

        try {
            $filesystem->url('releasekit-probe');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function supportsTemporaryUrls(string $disk): bool
    {
        $filesystem = $this->disk($disk);

        return $filesystem instanceof FilesystemAdapter && $filesystem->providesTemporaryUrls();
    }

    private function stream(ArtifactData $artifact): StreamedResponse
    {
        $disk = $this->disk($artifact->disk);

        $headers = array_filter([
            'Content-Type' => $artifact->mimeType ?? 'application/octet-stream',
            'Content-Length' => $artifact->sizeBytes,
            'Content-Disposition' => $this->disposition($artifact),
        ], fn ($value) => $value !== null);

        return new StreamedResponse(function () use ($disk, $artifact) {
            $stream = $disk->readStream($artifact->path);

            if (! is_resource($stream)) {
                return;
            }

            fpassthru($stream);
            fclose($stream);
        }, 200, $headers);
    }

    private function temporaryUrl(ArtifactData $artifact): string
    {
        /** @var FilesystemAdapter $disk */
        $disk = $this->disk($artifact->disk);

        return $disk->temporaryUrl($artifact->path, now()->addSeconds($this->ttl()), array_filter([
            // Honoured by S3-compatible drivers; ignored elsewhere.
            'ResponseContentDisposition' => $this->disposition($artifact),
            'ResponseContentType' => $artifact->mimeType,
        ]));
    }

    private function disposition(ArtifactData $artifact): string
    {
        $fallback = preg_replace('/[^\x20-\x7e]|[%\/\\\\"]/', '_', $artifact->originalFilename) ?: 'download';

        return HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $artifact->originalFilename, $fallback);
    }

    private function ttl(): int
    {
        return (int) $this->config->get('releasekit.delivery.signed_url_ttl', 300);
    }

    private function disk(string $disk): Filesystem
    {
        return $this->filesystem->disk($disk);
    }

    private function warnOnce(string $disk, string $mode, string $message): void
    {
        $key = "{$disk}:{$mode}";

        if (isset(self::$warned[$key])) {
            return;
        }

        self::$warned[$key] = true;

        Log::warning("ReleaseKit: {$message}", ['disk' => $disk, 'mode' => $mode]);
    }

    /**
     * @internal For tests.
     */
    public static function resetWarnings(): void
    {
        self::$warned = [];
    }
}
