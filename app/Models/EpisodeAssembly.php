<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssemblyStatus;
use Database\Factories\EpisodeAssemblyFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $episode_revision_id
 * @property int $number
 * @property AssemblyStatus $status
 * @property array<string, mixed> $manifest
 * @property string|null $manifest_hash
 * @property array<string, mixed> $continuity_snapshot
 * @property int $lock_version
 * @property int|null $duration_ms
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $accepted_at
 * @property int|null $accepted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EpisodeRevision|null $episodeRevision
 * @property-read User|null $acceptedBy
 * @property-read Collection<int, PublicationRequest> $publications
 * @property-read MediaCollection<int, Media> $media
 */
class EpisodeAssembly extends Model implements HasMedia
{
    /** @use HasFactory<EpisodeAssemblyFactory> */
    use HasFactory;

    use InteractsWithMedia;

    protected $fillable = [
        'episode_revision_id',
        'number',
        'status',
        'manifest',
        'manifest_hash',
        'continuity_snapshot',
        'lock_version',
        'duration_ms',
        'error_message',
        'started_at',
        'completed_at',
        'accepted_at',
        'accepted_by',
    ];

    protected $casts = [
        'number' => 'integer',
        'status' => AssemblyStatus::class,
        'manifest' => 'array',
        'continuity_snapshot' => 'array',
        'lock_version' => 'integer',
        'duration_ms' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    /** @return BelongsTo<EpisodeRevision, $this> */
    public function episodeRevision(): BelongsTo
    {
        return $this->belongsTo(EpisodeRevision::class);
    }

    /** @return BelongsTo<User, $this> */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    /** @return HasMany<PublicationRequest, $this> */
    public function publications(): HasMany
    {
        return $this->hasMany(PublicationRequest::class);
    }

    public const string VIDEO = 'episode-video';

    public const string CONTINUATION_FRAME = 'continuation-frame';

    public const string CONTINUATION_TAIL = 'continuation-tail';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::VIDEO)->useDisk('local');
        $this->addMediaCollection(self::CONTINUATION_FRAME)->useDisk('local');
        $this->addMediaCollection(self::CONTINUATION_TAIL)->useDisk('local');
    }
}
