<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RevisionStatus;
use Database\Factories\EpisodeRevisionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $episode_id
 * @property int $number
 * @property RevisionStatus $status
 * @property string $title
 * @property string $objective
 * @property int $schema_version
 * @property array<string, mixed> $context_snapshot
 * @property string|null $context_hash
 * @property int|null $source_assembly_id
 * @property int $base_continuity_version
 * @property int $lock_version
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Episode|null $episode
 * @property-read Collection<int, Scene> $scenes
 * @property-read Collection<int, EpisodeAssembly> $assemblies
 * @property-read Collection<int, CharacterRevision> $characterRevisions
 * @property-read EpisodeAssembly|null $sourceAssembly
 * @property-read User|null $approvedBy
 */
class EpisodeRevision extends Model
{
    /** @use HasFactory<EpisodeRevisionFactory> */
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'number',
        'status',
        'title',
        'objective',
        'schema_version',
        'context_snapshot',
        'context_hash',
        'source_assembly_id',
        'base_continuity_version',
        'lock_version',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'number' => 'integer',
        'status' => RevisionStatus::class,
        'schema_version' => 'integer',
        'context_snapshot' => 'array',
        'base_continuity_version' => 'integer',
        'lock_version' => 'integer',
        'approved_at' => 'datetime',
    ];

    /** @return BelongsTo<Episode, $this> */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /** @return HasMany<Scene, $this> */
    public function scenes(): HasMany
    {
        return $this->hasMany(Scene::class)->orderBy('position');
    }

    /** @return HasMany<EpisodeAssembly, $this> */
    public function assemblies(): HasMany
    {
        return $this->hasMany(EpisodeAssembly::class);
    }

    /** @return BelongsToMany<CharacterRevision, $this> */
    public function characterRevisions(): BelongsToMany
    {
        return $this->belongsToMany(CharacterRevision::class, 'episode_revision_characters');
    }

    /** @return BelongsTo<EpisodeAssembly, $this> */
    public function sourceAssembly(): BelongsTo
    {
        return $this->belongsTo(EpisodeAssembly::class, 'source_assembly_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
