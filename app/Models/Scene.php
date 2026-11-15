<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SceneFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $episode_revision_id
 * @property int $position
 * @property string $title
 * @property array<string, mixed> $setting
 * @property string $objective
 * @property array<string, mixed> $initial_state
 * @property array<string, mixed> $expected_final_state
 * @property bool $is_closed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EpisodeRevision|null $episodeRevision
 * @property-read Collection<int, Shot> $shots
 */
class Scene extends Model
{
    /** @use HasFactory<SceneFactory> */
    use HasFactory;

    protected $fillable = [
        'episode_revision_id',
        'position',
        'title',
        'setting',
        'objective',
        'initial_state',
        'expected_final_state',
        'is_closed',
    ];

    protected $casts = [
        'position' => 'integer',
        'setting' => 'array',
        'initial_state' => 'array',
        'expected_final_state' => 'array',
        'is_closed' => 'boolean',
    ];

    /** @return BelongsTo<EpisodeRevision, $this> */
    public function episodeRevision(): BelongsTo
    {
        return $this->belongsTo(EpisodeRevision::class);
    }

    /** @return HasMany<Shot, $this> */
    public function shots(): HasMany
    {
        return $this->hasMany(Shot::class)->orderBy('position');
    }
}
