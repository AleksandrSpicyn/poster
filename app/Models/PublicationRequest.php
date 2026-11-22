<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PublicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int|null $prompt_id
 * @property int $id
 * @property int|null $series_assembly_id
 * @property string|null $release_key
 * @property int|null $episode_assembly_id
 * @property int $channel_id
 * @property int $created_by
 * @property int|null $production_run_id
 * @property PublicationStatus $status
 * @property array{title: string, description: string, made_for_kids: bool, use_public_description?: bool, tiktok?: array<string, mixed>} $payload
 * @property string|null $container_id
 * @property string|null $error_message
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $published_at
 * @property-read Channel|null $channel
 * @property-read EpisodeAssembly|null $assembly
 */
class PublicationRequest extends Model
{
    protected $fillable = ['prompt_id', 'series_assembly_id', 'release_key', 'episode_assembly_id', 'channel_id', 'created_by', 'production_run_id', 'status', 'payload', 'container_id', 'error_message', 'scheduled_at', 'submitted_at', 'published_at'];

    protected $casts = ['status' => PublicationStatus::class, 'payload' => 'array', 'scheduled_at' => 'datetime', 'submitted_at' => 'datetime', 'published_at' => 'datetime'];

    /** @return BelongsTo<Prompt, $this> */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    /** @return BelongsTo<SeriesAssembly, $this> */
    public function seriesAssembly(): BelongsTo
    {
        return $this->belongsTo(SeriesAssembly::class);
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** @return BelongsTo<EpisodeAssembly, $this> */
    public function assembly(): BelongsTo
    {
        return $this->belongsTo(EpisodeAssembly::class, 'episode_assembly_id');
    }
}
