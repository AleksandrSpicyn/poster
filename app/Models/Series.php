<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SeriesFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property array<string, mixed> $specification
 * @property int $specification_version
 * @property int $continuity_version
 * @property int|null $canonical_assembly_id
 * @property int $lock_version
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project|null $project
 * @property-read Collection<int, Character> $characters
 * @property-read Collection<int, Episode> $episodes
 * @property-read EpisodeAssembly|null $canonicalAssembly
 */
class Series extends Model
{
    /** @use HasFactory<SeriesFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'specification',
        'specification_version',
        'continuity_version',
        'canonical_assembly_id',
        'lock_version',
        'archived_at',
    ];

    protected $casts = [
        'specification' => 'array',
        'specification_version' => 'integer',
        'continuity_version' => 'integer',
        'lock_version' => 'integer',
        'archived_at' => 'datetime',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<Character, $this> */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    /** @return HasMany<Episode, $this> */
    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class);
    }

    /** @return BelongsTo<EpisodeAssembly, $this> */
    public function canonicalAssembly(): BelongsTo
    {
        return $this->belongsTo(EpisodeAssembly::class, 'canonical_assembly_id');
    }
}
