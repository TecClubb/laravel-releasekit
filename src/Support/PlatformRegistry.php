<?php

namespace TecClub\ReleaseKit\Support;

use Illuminate\Contracts\Config\Repository;

/**
 * Read access to the platforms and stores defined in config('releasekit').
 *
 * Unknown keys never throw: labels fall back to the raw key, so rows that
 * still reference a removed platform or store keep rendering.
 */
class PlatformRegistry
{
    public function __construct(private readonly Repository $config) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return (array) $this->config->get('releasekit.platforms', []);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function has(string $platform): bool
    {
        return array_key_exists($platform, $this->all());
    }

    public function label(string $platform): string
    {
        return (string) ($this->all()[$platform]['label'] ?? $platform);
    }

    /**
     * @return list<string>
     */
    public function architectures(string $platform): array
    {
        return $this->list($platform, 'architectures');
    }

    /**
     * @return list<string>
     */
    public function formats(string $platform): array
    {
        return $this->list($platform, 'formats');
    }

    /**
     * Formats the API may offer. Always a subset of formats().
     *
     * @return list<string>
     */
    public function downloadableFormats(string $platform): array
    {
        return array_values(array_intersect(
            $this->list($platform, 'downloadable_formats'),
            $this->formats($platform),
        ));
    }

    /**
     * @return list<string>
     */
    public function stores(string $platform): array
    {
        return $this->list($platform, 'stores');
    }

    public function supportsArchitecture(string $platform, string $architecture): bool
    {
        return in_array($architecture, $this->architectures($platform), true);
    }

    public function supportsFormat(string $platform, string $format): bool
    {
        return in_array($format, $this->formats($platform), true);
    }

    public function isDownloadableFormat(string $platform, string $format): bool
    {
        return in_array($format, $this->downloadableFormats($platform), true);
    }

    /**
     * The store exists in config and is allowed for the platform.
     */
    public function supportsStore(string $platform, string $store): bool
    {
        return $this->hasStore($store) && in_array($store, $this->stores($platform), true);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function allStores(): array
    {
        return (array) $this->config->get('releasekit.stores', []);
    }

    public function hasStore(string $store): bool
    {
        return array_key_exists($store, $this->allStores());
    }

    public function storeLabel(string $store): string
    {
        return (string) ($this->allStores()[$store]['label'] ?? $store);
    }

    /**
     * @return list<string>
     */
    private function list(string $platform, string $key): array
    {
        return array_values(array_map('strval', (array) ($this->all()[$platform][$key] ?? [])));
    }
}
