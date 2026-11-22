<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RevisionStatus;
use Database\Factories\CharacterRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $character_id
 * @property int $number
 * @property RevisionStatus $status
 * @property string|null $title
 * @property string $description
 * @property array<string, mixed> $appearance
 * @property array<string, mixed> $voice
 * @property int $lock_version
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Character|null $character
 * @property-read User|null $approvedBy
 * @property-read MediaCollection<int, Media> $media
 */
class CharacterRevision extends Model implements HasMedia
{
    /** @use HasFactory<CharacterRevisionFactory> */
    use HasFactory;

    use InteractsWithMedia;

    protected $fillable = [
        'character_id',
        'number',
        'status',
        'title',
        'description',
        'appearance',
        'voice',
        'lock_version',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'number' => 'integer',
        'status' => RevisionStatus::class,
        'appearance' => 'array',
        'voice' => 'array',
        'lock_version' => 'integer',
        'approved_at' => 'datetime',
    ];

    /** @return BelongsTo<Character, $this> */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public const string REFERENCES = 'character-references';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::REFERENCES)
            ->useDisk('local')
            ->acceptsMimeTypes(['image/png', 'image/jpeg', 'image/webp']);
    }

    protected static function booted(): void
    {
        static::updating(static function (CharacterRevision $revision): void {
            if ($revision->getRawOriginal('status') !== RevisionStatus::DRAFT->value && $revision->isDirty()) {
                throw ValidationException::withMessages(['revision' => 'An approved character version cannot be changed.']);
            }
        });

        static::deleting(static function (CharacterRevision $revision): void {
            if ($revision->status !== RevisionStatus::DRAFT) {
                throw ValidationException::withMessages(['revision' => 'Archive the character instead of deleting an approved version.']);
            }
        });
    }
}
