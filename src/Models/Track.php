<?php

namespace TecClub\ReleaseKit\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use TecClub\ReleaseKit\Database\Factories\TrackFactory;
use TecClub\ReleaseKit\Support\ModelResolver;

/**
 * One row per platform + channel. Decides what clients receive.
 *
 * @property int $id
 * @property string $platform
 * @property string $channel
 * @property int|null $current_release_id
 * @property string|null $minimum_supported_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Release|null $currentRelease
 */
class Track extends Model
{
    /** @use HasFactory<TrackFactory> */
    use HasFactory;

    protected $fillable = [
        'platform',
        'channel',
        'current_release_id',
        'minimum_supported_version',
    ];

    public function getTable(): string
    {
        return ModelResolver::table('tracks');
    }

    /**
     * @return BelongsTo<Release, $this>
     */
    public function currentRelease(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::release(), 'current_release_id');
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

    protected static function newFactory(): TrackFactory
    {
        return TrackFactory::new();
    }
}
