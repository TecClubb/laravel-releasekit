<?php

namespace TecClub\ReleaseKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use TecClub\ReleaseKit\Support\ModelResolver;

/**
 * Daily aggregate counts (optional analytics). Stores no IPs, user agents
 * or device IDs. Requires the published analytics migration.
 *
 * @property int $id
 * @property Carbon $date
 * @property string $event
 * @property string $platform
 * @property string $channel
 * @property string $version
 * @property string $architecture
 * @property int $artifact_id
 * @property string $country
 * @property int $count
 */
class Stat extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'date',
        'event',
        'platform',
        'channel',
        'version',
        'architecture',
        'artifact_id',
        'country',
        'count',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'artifact_id' => 'integer',
            'count' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return ModelResolver::table('stats');
    }
}
