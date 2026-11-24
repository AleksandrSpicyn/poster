<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\Episodes\EpisodeAssemblyPlanData;
use App\Enums\UpscaleOperationStatus;
use App\Enums\UpscaleProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $episode_assembly_id
 * @property int $initiator_id
 * @property int|null $source_media_id
 * @property int|null $result_media_id
 * @property string|null $source_sha256
 * @property string|null $result_sha256
 * @property int $estimated_cost_e8
 * @property int|null $actual_cost_e8
 * @property int $attempt
 * @property UpscaleProvider $provider
 * @property UpscaleOperationStatus $status
 * @property array<string, mixed> $source_snapshot
 * @property array<string, mixed> $profile_snapshot
 * @property array<string, string>|null $receipt
 */
class VideoUpscaleOperation extends Model
{
    protected $fillable = [
        'episode_assembly_id', 'initiator_id', 'operation_key', 'attempt', 'provider',
        'status', 'source_snapshot', 'profile_snapshot', 'estimated_cost_e8', 'currency',
    ];

    protected $hidden = ['receipt', 'source_snapshot'];

    protected $casts = [
        'attempt' => 'integer',
        'provider' => UpscaleProvider::class,
        'status' => UpscaleOperationStatus::class,
        'source_snapshot' => 'array',
        'profile_snapshot' => 'array',
        'receipt' => 'array',
        'last_error_at' => 'datetime',
    ];

    public function durationMs(): int
    {
        return EpisodeAssemblyPlanData::fromManifest($this->source_snapshot)->durationMs;
    }

    /** @return BelongsTo<EpisodeAssembly, $this> */
    public function episodeAssembly(): BelongsTo
    {
        return $this->belongsTo(EpisodeAssembly::class);
    }
}
