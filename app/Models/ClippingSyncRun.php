<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $opus_source_id
 * @property int $clips_received
 * @property int $clips_added
 * @property int $publications_added
 * @property Carbon|null $failed_at
 * @property Carbon|null $resumed_at
 * @property Carbon|null $completed_at
 */
final class ClippingSyncRun extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['clips_completed_at' => 'datetime', 'failed_at' => 'datetime', 'resumed_at' => 'datetime', 'completed_at' => 'datetime'];

    /** @return BelongsTo<OpusSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(OpusSource::class, 'opus_source_id');
    }
}
