<?php

namespace TecClub\ReleaseKit\Services;

use TecClub\ReleaseKit\Data\CheckResult;
use TecClub\ReleaseKit\Data\TrackSnapshot;
use TecClub\ReleaseKit\Enums\CheckStatus;
use TecClub\ReleaseKit\Support\ChannelRegistry;
use TecClub\ReleaseKit\Support\Concerns\ValidatesInput;
use TecClub\ReleaseKit\Support\VersionComparator;

/**
 * Answers "is this client up to date?". Evaluated in order, first match wins:
 *
 * 1. The client's version equals a yanked release → blocked
 * 2. Below the minimum supported version          → update_required
 * 3. Below the current release                    → update_available
 * 4. Otherwise (incl. dev builds newer than current) → up_to_date
 *
 * With no track or no current release, rule 2 still applies when a
 * minimum is set. The client-independent state comes from the cache;
 * only these comparisons run per request.
 */
class VersionChecker
{
    use ValidatesInput;

    public function __construct(
        private readonly TrackSnapshots $snapshots,
        private readonly DownloadResolver $downloads,
        private readonly ChannelRegistry $channels,
    ) {}

    public function check(string $platform, string $version, ?string $channel = null, ?string $architecture = null): CheckResult
    {
        $channel ??= $this->channels->default();

        $this->assertPlatform($platform);
        $this->assertChannel($channel);
        VersionComparator::assertValid($version);

        $snapshot = $this->snapshots->get($platform, $channel);

        return new CheckResult(
            status: $this->status($snapshot, $version),
            clientVersion: $version,
            minimumSupportedVersion: $snapshot->minimumSupportedVersion,
            current: $snapshot->current,
            downloads: $this->downloads->fromSnapshot($snapshot, $architecture),
        );
    }

    public function status(TrackSnapshot $snapshot, string $version): CheckStatus
    {
        foreach ($snapshot->yankedVersions as $yanked) {
            if (VersionComparator::isValid($yanked) && VersionComparator::equals($version, $yanked)) {
                return CheckStatus::Blocked;
            }
        }

        $minimum = $snapshot->minimumSupportedVersion;

        if ($minimum !== null && VersionComparator::isValid($minimum) && VersionComparator::lessThan($version, $minimum)) {
            return CheckStatus::UpdateRequired;
        }

        $current = $snapshot->current?->version;

        if ($current !== null && VersionComparator::isValid($current) && VersionComparator::lessThan($version, $current)) {
            return CheckStatus::UpdateAvailable;
        }

        return CheckStatus::UpToDate;
    }
}
