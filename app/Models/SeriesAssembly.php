<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssemblyStatus;
use Database\Factories\SeriesAssemblyFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $series_id
 * @property int $number
 * @property AssemblyStatus $status
 * @property array<string, mixed> $manifest
 * @property string $manifest_hash
 * @property int $lock_version
 * @property int|null $duration_ms
 * @property string|null $error_message
 * @property array<string, mixed>|null $review
 * @property Carbon|null $started_at
 * @property Carbon|null $accepted_at
 * @property-read Collection<int, EpisodeAssembly> $episodeAssemblies
 * @property-read Series $series
 */
class SeriesAssembly extends Model implements HasMedia
{
    /** @use HasFactory<SeriesAssemblyFactory> */
    use HasFactory;
    use InteractsWithMedia;

    public const string VIDEO = 'series-video';

    protected $guarded = ['id'];

    protected $casts = [
        'status' => AssemblyStatus::class,
        'manifest' => 'array',
        'review' => 'array',
        'lock_version' => 'integer',
        'duration_ms' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    /** @return BelongsTo<Series, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    /** @return BelongsToMany<EpisodeAssembly, $this, Pivot, 'pivot'> */
    public function episodeAssemblies(): BelongsToMany
    {
        return $this->belongsToMany(EpisodeAssembly::class, 'series_assembly_items')->orderByPivot('position');
    }

    /** @return HasMany<PublicationRequest, $this> */
    public function publications(): HasMany
    {
        return $this->hasMany(PublicationRequest::class);
    }

    /** @return HasMany<VideoUpscaleOperation, $this> */
    public function upscales(): HasMany
    {
        return $this->hasMany(VideoUpscaleOperation::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::VIDEO)->useDisk('local')->singleFile();
    }
}
