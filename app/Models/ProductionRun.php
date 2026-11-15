<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use Database\Factories\ProductionRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $series_id
 * @property int|null $actor_id
 * @property int|null $episode_id
 * @property int|null $prompt_id
 * @property int|null $episode_assembly_id
 * @property string $request_key
 * @property ProductionStatus $status
 * @property ProductionStage $stage
 * @property array<string, mixed>|null $outcome_snapshot
 * @property string|null $error_message
 * @property Carbon|null $next_check_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Project $project
 * @property-read Series|null $series
 * @property-read User|null $actor
 * @property-read Episode|null $episode
 * @property-read Prompt|null $prompt
 * @property-read EpisodeAssembly|null $assembly
 */
class ProductionRun extends Model
{
    /** @use HasFactory<ProductionRunFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => ProductionStatus::class,
        'stage' => ProductionStage::class,
        'outcome_snapshot' => 'array',
        'next_check_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Series, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<Episode, $this> */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /** @return BelongsTo<Prompt, $this> */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    /** @return BelongsTo<EpisodeAssembly, $this> */
    public function assembly(): BelongsTo
    {
        return $this->belongsTo(EpisodeAssembly::class, 'episode_assembly_id');
    }
}
