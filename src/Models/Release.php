<?php

namespace TecClub\ReleaseKit\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use TecClub\ReleaseKit\Database\Factories\ReleaseFactory;
use TecClub\ReleaseKit\Enums\ReleaseStatus;
use TecClub\ReleaseKit\Support\ChannelRegistry;
use TecClub\ReleaseKit\Support\ModelResolver;
use TecClub\ReleaseKit\Support\PlatformRegistry;

/**
 * One row per platform + channel + version.
 *
 * Write through the Actions in TecClub\ReleaseKit\Actions; read freely.
 *
 * @property int $id
 * @property string $platform
 * @property string $channel
 * @property string $version
 * @property string|null $build_number
 * @property ReleaseStatus $status
 * @property string|null $release_notes
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $published_at
 * @property Carbon|null $yanked_at
 * @property string|null $yank_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Release extends Model
{
    /** @use HasFactory<ReleaseFactory> */
    use HasFactory;

    protected $fillable = [
        'platform',
        'channel',
        'version',
        'build_number',
        'status',
        'release_notes',
        'metadata',
        'published_at',
        'yanked_at',
        'yank_reason',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReleaseStatus::class,
            'metadata' => 'array',
            'published_at' => 'datetime',
            'yanked_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return ModelResolver::table('releases');
    }

    /**
     * @return HasMany<Artifact, $this>
     */
    public function artifacts(): HasMany
    {
        return $this->hasMany(ModelResolver::artifact(), 'app_release_id');
    }

    /**
     * Release-specific links. Permanent platform links have no release.
     *
     * @return HasMany<Link, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(ModelResolver::link(), 'app_release_id');
    }

    /**
     * The track this release is currently live on, if any.
     *
     * @return HasOne<Track, $this>
     */
    public function currentOnTrack(): HasOne
    {
        return $this->hasOne(ModelResolver::track(), 'current_release_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForPlatform(Builder $query, string $platform): void
    {
        $query->where('platform', $platform);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForChannel(Builder $query, string $channel): void
    {
        $query->where('channel', $channel);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeWithStatus(Builder $query, ReleaseStatus $status): void
    {
        $query->where('status', $status->value);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ReleaseStatus::Published->value);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeDrafts(Builder $query): void
    {
        $query->where('status', ReleaseStatus::Draft->value);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeYanked(Builder $query): void
    {
        $query->where('status', ReleaseStatus::Yanked->value);
    }

    public function isDraft(): bool
    {
        return $this->status === ReleaseStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === ReleaseStatus::Published;
    }

    public function isYanked(): bool
    {
        return $this->status === ReleaseStatus::Yanked;
    }

    public function platformLabel(): string
    {
        return app(PlatformRegistry::class)->label($this->platform);
    }

    public function channelLabel(): string
    {
        return app(ChannelRegistry::class)->label($this->channel);
    }

    /**
     * Human-readable name for messages, e.g. "Android 1.2.0 (stable)".
     */
    public function displayName(): string
    {
        return "{$this->platformLabel()} {$this->version} ({$this->channelLabel()})";
    }

    protected static function newFactory(): ReleaseFactory
    {
        return ReleaseFactory::new();
    }
}
