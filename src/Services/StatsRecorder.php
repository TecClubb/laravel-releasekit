<?php

namespace TecClub\ReleaseKit\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use TecClub\ReleaseKit\Enums\StatEvent;
use TecClub\ReleaseKit\Models\Artifact;
use TecClub\ReleaseKit\Support\ModelResolver;
use Throwable;

/**
 * Optional daily aggregate counts. Stores no IPs, user agents or device IDs.
 *
 * Does nothing unless releasekit.analytics.enabled is true and the stats
 * table exists (published with --tag=releasekit-analytics-migrations).
 * A failed write is logged and swallowed: analytics never break a check
 * or a download.
 *
 * Each record is one atomic upsert that increments "count" for its
 * (date, event, platform, channel, version, architecture, artifact_id,
 * country) row. Empty dimensions are stored as '' and 0, never NULL, so
 * the unique index matches them.
 */
class StatsRecorder
{
    private ?bool $tableExists = null;

    public function __construct(private readonly Repository $config) {}

    public function enabled(): bool
    {
        if (! $this->config->get('releasekit.analytics.enabled', false)) {
            return false;
        }

        return $this->tableExists ??= $this->detectTable();
    }

    public function recordCheck(string $platform, string $channel, string $clientVersion, ?string $architecture = null, ?string $country = null): void
    {
        if (! $this->config->get('releasekit.analytics.track_checks', true) || ! $this->enabled()) {
            return;
        }

        $this->increment(StatEvent::Check, [
            'platform' => $platform,
            'channel' => $channel,
            'version' => $clientVersion,
            'architecture' => $architecture ?? '',
            'artifact_id' => 0,
            'country' => $country ?? '',
        ]);
    }

    /**
     * A download initiated through the app. Counted by the release version.
     */
    public function recordDownload(Artifact $artifact, ?string $country = null): void
    {
        if (! $this->config->get('releasekit.analytics.track_downloads', true) || ! $this->enabled()) {
            return;
        }

        $release = $artifact->release;

        $this->increment(StatEvent::Download, [
            'platform' => $release->platform,
            'channel' => $release->channel,
            'version' => $release->version,
            'architecture' => $artifact->architecture ?? '',
            'artifact_id' => (int) $artifact->getKey(),
            'country' => $country ?? '',
        ]);
    }

    /**
     * The two-letter country from the configured header (e.g. Cloudflare's
     * CF-IPCountry), or null when disabled, missing or malformed.
     */
    public function countryFrom(Request $request): ?string
    {
        $header = $this->config->get('releasekit.analytics.country_header');

        if (! is_string($header) || $header === '') {
            return null;
        }

        $country = strtoupper(trim((string) $request->header($header)));

        return preg_match('/^[A-Z][A-Z0-9]$/', $country) === 1 ? $country : null;
    }

    /**
     * @param  array{platform: string, channel: string, version: string, architecture: string, artifact_id: int, country: string}  $dimensions
     */
    private function increment(StatEvent $event, array $dimensions): void
    {
        try {
            $model = ModelResolver::stat();
            $query = $model::query();
            $grammar = $query->getQuery()->getGrammar();
            $table = $query->getModel()->getTable();

            $row = ['date' => now()->toDateString(), 'event' => $event->value, ...$dimensions];

            $query->upsert(
                [[...$row, 'count' => 1]],
                array_keys($row),
                // Qualified with the table so it reads the stored row on every engine.
                ['count' => $query->getQuery()->raw($grammar->wrapTable($table).'.'.$grammar->wrap('count').' + 1')],
            );
        } catch (Throwable $e) {
            Log::warning('ReleaseKit could not record a stat.', ['event' => $event->value, 'error' => $e->getMessage()]);
        }
    }

    private function detectTable(): bool
    {
        try {
            $model = ModelResolver::stat();
            $instance = new $model;

            return $instance->getConnection()->getSchemaBuilder()->hasTable($instance->getTable());
        } catch (Throwable) {
            return false;
        }
    }
}
