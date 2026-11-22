<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CharacterFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $series_id
 * @property string|null $story_role
 * @property string $name
 * @property int|null $current_revision_id
 * @property int $lock_version
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Series|null $series
 * @property-read Collection<int, CharacterRevision> $revisions
 * @property-read CharacterRevision|null $currentRevision
 */
class Character extends Model
{
    /** @use HasFactory<CharacterFactory> */
    use HasFactory;

    protected $fillable = [
        'series_id',
        'name',
        'story_role',
        'current_revision_id',
        'lock_version',
        'archived_at',
    ];

    protected $casts = [
        'lock_version' => 'integer',
        'archived_at' => 'datetime',
    ];

    /** @return BelongsTo<Series, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    /** @return HasMany<CharacterRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(CharacterRevision::class);
    }

    /** @return BelongsTo<CharacterRevision, $this> */
    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(CharacterRevision::class, 'current_revision_id');
    }
}
