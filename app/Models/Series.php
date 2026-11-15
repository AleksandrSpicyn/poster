<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductionStatus;
use Database\Factories\SeriesFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @property bool $automation_enabled
 * @property int|null $automation_user_id
 * @property int|null $episode_limit
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project|null $project
 * @property-read Collection<int, Character> $characters
 * @property-read Collection<int, Episode> $episodes
 * @property-read EpisodeAssembly|null $canonicalAssembly
 * @property-read User|null $automationUser
 * @property-read Collection<int, ProductionRun> $productionRuns
 * @property-read ProductionRun|null $latestProductionRun
 * @property-read ProductionRun|null $activeProductionRun
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
        'automation_enabled',
        'automation_user_id',
        'episode_limit',
    ];

    protected $casts = [
        'specification' => 'array',
        'specification_version' => 'integer',
        'continuity_version' => 'integer',
        'lock_version' => 'integer',
        'archived_at' => 'datetime',
        'automation_enabled' => 'boolean',
        'episode_limit' => 'integer',
    ];

    protected $attributes = ['automation_enabled' => false];

    /** @return BelongsTo<User, $this> */
    public function automationUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'automation_user_id');
    }

    /** @return HasMany<ProductionRun, $this> */
    public function productionRuns(): HasMany
    {
        return $this->hasMany(ProductionRun::class);
    }

    /** @return HasOne<ProductionRun, $this> */
    public function latestProductionRun(): HasOne
    {
        return $this->hasOne(ProductionRun::class)->latestOfMany();
    }

    /** @return HasOne<ProductionRun, $this> */
    public function activeProductionRun(): HasOne
    {
        return $this->hasOne(ProductionRun::class)->whereIn('status', ProductionStatus::blockingValues());
    }

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
