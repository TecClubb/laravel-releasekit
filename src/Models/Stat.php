<?php

namespace TecClub\ReleaseKit\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use TecClub\ReleaseKit\Enums\StatEvent;
use TecClub\ReleaseKit\Support\ModelResolver;

/**
 * Daily aggregate counts (optional analytics). Stores no IPs, user agents
 * or device IDs. Requires the published analytics migration.
 *
 * Rows are written by StatsRecorder; hosts read them, e.g.:
 *
 *     Stat::event(StatEvent::Check)->forPlatform('android')->between($from, $to)->groupByVersion()->get();
 *
 * Grouping scopes select the group column plus "total" (sum of count);
 * ties are ordered by the group column.
 *
 * @property int $id
 * @property Carbon $date
 * @property StatEvent $event
 * @property string $platform
 * @property string $channel
 * @property string $version Client version for checks, release version for downloads; '' when unknown
 * @property string $architecture '' when not given
 * @property int $artifact_id 0 for checks
 * @property string $country Two letters, '' when unknown
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
            'event' => StatEvent::class,
            'artifact_id' => 'integer',
            'count' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return ModelResolver::table('stats');
    }

    /**
     * Inclusive date range.
     *
     * @param  Builder<static>  $query
     */
    public function scopeBetween(Builder $query, DateTimeInterface|string $from, DateTimeInterface|string $to): void
    {
        $query->whereBetween('date', [Carbon::parse($from)->toDateString(), Carbon::parse($to)->toDateString()]);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeEvent(Builder $query, StatEvent|string $event): void
    {
        $query->where('event', $event instanceof StatEvent ? $event->value : $event);
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
    public function scopeForArtifact(Builder $query, Artifact|int $artifact): void
    {
        $query->where('artifact_id', $artifact instanceof Artifact ? $artifact->getKey() : $artifact);
    }

    /**
     * Totals per version, highest first.
     *
     * @param  Builder<static>  $query
     */
    public function scopeGroupByVersion(Builder $query): void
    {
        $this->groupWithTotal($query, 'version')->orderByDesc('total')->orderBy('version');
    }

    /**
     * Totals per day, oldest first.
     *
     * @param  Builder<static>  $query
     */
    public function scopeGroupByDate(Builder $query): void
    {
        $this->groupWithTotal($query, 'date')->orderBy('date');
    }

    /**
     * Totals per country, highest first.
     *
     * @param  Builder<static>  $query
     */
    public function scopeGroupByCountry(Builder $query): void
    {
        $this->groupWithTotal($query, 'country')->orderByDesc('total')->orderBy('country');
    }

    /**
     * Totals per platform, highest first.
     *
     * @param  Builder<static>  $query
     */
    public function scopeGroupByPlatform(Builder $query): void
    {
        $this->groupWithTotal($query, 'platform')->orderByDesc('total')->orderBy('platform');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    private function groupWithTotal(Builder $query, string $column): Builder
    {
        $grammar = $query->getQuery()->getGrammar();

        return $query
            ->select($column)
            ->selectRaw('SUM('.$grammar->wrap('count').') as '.$grammar->wrap('total'))
            ->groupBy($column);
    }
}
