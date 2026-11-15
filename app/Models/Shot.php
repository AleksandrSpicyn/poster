<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransitionMode;
use Database\Factories\ShotFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $scene_id
 * @property int $position
 * @property int $duration_ms
 * @property string $action
 * @property array<string, mixed> $camera
 * @property list<array<string, mixed>> $dialogue
 * @property TransitionMode $transition_mode
 * @property int|null $source_prompt_id
 * @property int|null $source_assembly_id
 * @property array<string, mixed> $source_anchor
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Scene|null $scene
 * @property-read Collection<int, Prompt> $prompts
 * @property-read Prompt|null $sourcePrompt
 * @property-read EpisodeAssembly|null $sourceAssembly
 */
class Shot extends Model
{
    /** @use HasFactory<ShotFactory> */
    use HasFactory;

    protected $fillable = [
        'scene_id',
        'position',
        'duration_ms',
        'action',
        'camera',
        'dialogue',
        'transition_mode',
        'source_prompt_id',
        'source_assembly_id',
        'source_anchor',
    ];

    protected $casts = [
        'position' => 'integer',
        'duration_ms' => 'integer',
        'camera' => 'array',
        'dialogue' => 'array',
        'transition_mode' => TransitionMode::class,
        'source_anchor' => 'array',
    ];

    /** @return BelongsTo<Scene, $this> */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    /** @return HasMany<Prompt, $this> */
    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }

    /** @return BelongsTo<Prompt, $this> */
    public function sourcePrompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class, 'source_prompt_id');
    }

    /** @return BelongsTo<EpisodeAssembly, $this> */
    public function sourceAssembly(): BelongsTo
    {
        return $this->belongsTo(EpisodeAssembly::class, 'source_assembly_id');
    }
}
