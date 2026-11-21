<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, int|float> $metrics
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
