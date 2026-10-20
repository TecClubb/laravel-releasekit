<?php

namespace TecClub\ReleaseKit\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use TecClub\ReleaseKit\Database\Factories\ArtifactFactory;
use TecClub\ReleaseKit\Support\ModelResolver;

/**
 * An uploaded build file. The bytes live on the filesystem disk; one
 * artifact owns exactly one file.
 *
 * @property int $id
 * @property int $app_release_id
 * @property string|null $architecture null = universal
 * @property string $format
 * @property string|null $label
 * @property string $disk
 * @property string $path
 * @property string $original_filename
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property string|null $checksum_sha256
 * @property bool $is_active
 * @property int $sort_order
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Release $release
 */
class Artifact extends Model
{
    /** @use HasFactory<ArtifactFactory> */
    use HasFactory;

    protected $fillable = [
        'app_release_id',
        'architecture',
        'format',
        'label',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'checksum_sha256',
        'is_active',
        'sort_order',
        'metadata',
    ];

    /**
     * The storage location is never exposed by default.
     */
    protected $hidden = [
        'disk',
        'path',
    ];

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'app_release_id' => 'integer',
            'size_bytes' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function getTable(): string
    {
        return ModelResolver::table('artifacts');
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
     * Exact architecture matches plus universal (null) artifacts.
     * Passing null applies no filter.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForArchitecture(Builder $query, ?string $architecture): void
    {
        if ($architecture === null) {
            return;
        }

        $query->where(function (Builder $query) use ($architecture) {
            $query->where('architecture', $architecture)->orWhereNull('architecture');
        });
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    public function isUniversal(): bool
    {
        return $this->architecture === null;
    }

    protected static function newFactory(): ArtifactFactory
    {
        return ArtifactFactory::new();
    }
}
