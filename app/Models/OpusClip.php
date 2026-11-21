<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OpusClipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;

/**
 * @property int $id
 * @property-read OpusSource $source
 * @property int $opus_source_id
 * @property string $clip_id
 * @property list<array{platform: string, status: string|null, url: string|null, publish_at: string|null}> $publications
 */
class OpusClip extends Model
{
    /** @use HasFactory<OpusClipFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['publications' => 'array', 'score' => 'float', 'duration_ms' => 'integer'];

    /** @return BelongsTo<OpusSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(OpusSource::class, 'opus_source_id');
    }

    /** @return HasMany<OpusClipMetric, $this> */
    public function latestMetrics(): HasMany
    {
        return $this->hasMany(OpusClipMetric::class)->whereNotExists(function (Builder $query): void {
            $query->selectRaw('1')->from('opus_clip_metrics as newer')
                ->whereColumn('newer.opus_clip_id', 'opus_clip_metrics.opus_clip_id')
                ->whereColumn('newer.platform', 'opus_clip_metrics.platform')
                ->whereColumn('newer.media_id', 'opus_clip_metrics.media_id')
                ->whereColumn('newer.observed_on', '>', 'opus_clip_metrics.observed_on');
        });
    }
}
