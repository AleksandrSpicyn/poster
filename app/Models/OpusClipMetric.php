<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int|null $report_project_id
 * @property-read list<array{url?: string|null}>|null $clip_publications
 * @property array<string, int|float> $metrics
 * @property string $observed_on
 * @property string $platform
 * @property string $media_id
 * @property-read string $account Selected account from the dashboard query.
 * @property-read string|null $clip_game
 * @property-read string $clip_id
 * @property-read string|null $clip_title Selected clip title from the dashboard query.
 * @property-read OpusClip $clip
 */
class OpusClipMetric extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['metrics' => 'array'];

    /** @return BelongsTo<OpusClip, $this> */
    public function clip(): BelongsTo
    {
        return $this->belongsTo(OpusClip::class, 'opus_clip_id');
    }
}
