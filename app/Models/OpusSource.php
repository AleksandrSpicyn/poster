<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OpusSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $clipping_connection_id
 * @property string $external_id
 * @property string|null $publication_sync_checkpoint
 * @property string|null $title
 * @property string|null $game
 * @property Carbon|null $last_synced_at
 */
class OpusSource extends Model
{
    /** @use HasFactory<OpusSourceFactory> */
    use HasFactory;

    protected $casts = ['last_synced_at' => 'datetime', 'sync_paused_at' => 'datetime'];

    protected $hidden = ['publication_sync_checkpoint'];

    protected $guarded = ['id'];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
