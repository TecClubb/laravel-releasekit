<?php

namespace TecClub\ReleaseKit\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use TecClub\ReleaseKit\Database\Factories\LinkFactory;
use TecClub\ReleaseKit\Enums\LinkType;
use TecClub\ReleaseKit\Support\ModelResolver;
use TecClub\ReleaseKit\Support\PlatformRegistry;

/**
 * A store listing or external URL. Without a release it is a permanent
 * platform link; with one it belongs to that release only.
 *
 * @property int $id
 * @property string $platform
 * @property int|null $app_release_id
 * @property LinkType $type
 * @property string|null $store
 * @property string|null $label
 * @property string $url
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Release|null $release
 */
class Link extends Model
{
    /** @use HasFactory<LinkFactory> */
    use HasFactory;

    protected $fillable = [
        'platform',
        'app_release_id',
        'type',
        'store',
        'label',
        'url',
        'is_active',
        'sort_order',
    ];

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => LinkType::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return ModelResolver::table('links');
    }

    /**
     * @return BelongsTo<Release, $this>
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::release(), 'app_release_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForPlatform(Builder $query, string $platform): void
    {
        $query->where('platform', $platform);
    }

    /**
     * Permanent platform links (not tied to a release).
     *
     * @param  Builder<static>  $query
     */
    public function scopePermanent(Builder $query): void
    {
        $query->whereNull('app_release_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    public function isPermanent(): bool
    {
        return $this->app_release_id === null;
    }

    /**
     * The explicit label, else the store's label, else null.
     */
    public function displayLabel(): ?string
    {
        if ($this->label !== null && $this->label !== '') {
            return $this->label;
        }

        return $this->store !== null ? app(PlatformRegistry::class)->storeLabel($this->store) : null;
    }

    protected static function newFactory(): LinkFactory
    {
        return LinkFactory::new();
    }
}
