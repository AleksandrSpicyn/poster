<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EpisodeValidity;
use App\Enums\ScriptGenerationStatus;
use Database\Factories\EpisodeFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $series_id
 * @property int $number
 * @property string|null $objective
 * @property string $title
 * @property int|null $predecessor_episode_id
 * @property int|null $current_revision_id
 * @property int|null $accepted_assembly_id
 * @property int $lock_version
 * @property EpisodeValidity $validity
 * @property string|null $script_request_key
 * @property int|null $script_requested_by
 * @property ScriptGenerationStatus|null $script_generation_status
 * @property array{brief: string, context: array<string, mixed>, expected_version: int}|null $script_request_payload
 * @property string|null $script_generation_error
 * @property Carbon|null $script_started_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Series|null $series
 * @property-read Episode|null $predecessor
 * @property-read Collection<int, EpisodeRevision> $revisions
 * @property-read EpisodeRevision|null $currentRevision
 * @property-read EpisodeAssembly|null $acceptedAssembly
 */
class Episode extends Model
{
    /** @use HasFactory<EpisodeFactory> */
    use HasFactory;

    protected $fillable = [
        'series_id',
        'number',
        'title',
        'objective',
        'predecessor_episode_id',
        'current_revision_id',
        'accepted_assembly_id',
        'lock_version',
        'validity',
        'script_request_key', 'script_requested_by', 'script_generation_status', 'script_request_payload', 'script_generation_error', 'script_started_at',
    ];

    protected $casts = [
        'number' => 'integer',
        'lock_version' => 'integer',
        'validity' => EpisodeValidity::class,
        'script_generation_status' => ScriptGenerationStatus::class,
        'script_request_payload' => 'array',
        'script_started_at' => 'datetime',
    ];

    /** @return BelongsTo<Series, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    /** @return BelongsTo<Episode, $this> */
    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'predecessor_episode_id');
    }

    /** @return HasMany<EpisodeRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(EpisodeRevision::class);
    }

    /** @return BelongsTo<EpisodeRevision, $this> */
    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(EpisodeRevision::class, 'current_revision_id');
    }

    /** @return BelongsTo<EpisodeAssembly, $this> */
    public function acceptedAssembly(): BelongsTo
    {
        return $this->belongsTo(EpisodeAssembly::class, 'accepted_assembly_id');
    }
}
